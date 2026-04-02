<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Degiro;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\ParseWarning;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\CsvSanitizer;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

/**
 * Parses Degiro "Transactions" CSV export (flat format).
 *
 * Supports both English and Dutch/German column variants.
 * Quantity > 0 = BUY, Quantity < 0 = SELL.
 * Degiro does NOT include dividends in this export — see DegiroAccountStatementAdapter.
 */
final readonly class DegiroTransactionsAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;

    private const string BROKER_ID = 'degiro';

    private const string SECTION_NAME = 'Transactions';

    /**
     * English column names mapped to canonical keys.
     */
    private const array EN_COLUMNS = [
        'Date' => 'date',
        'Time' => 'time',
        'Product' => 'product',
        'ISIN' => 'isin',
        'Exchange' => 'exchange',
        'Execution Venue' => 'venue',
        'Quantity' => 'quantity',
        'Price' => 'price',
        'Local value' => 'local_value',
        'Value' => 'value',
        'Exchange rate' => 'exchange_rate',
        'Transaction costs' => 'transaction_costs',
        'Total' => 'total',
        'Order ID' => 'order_id',
    ];

    /**
     * Dutch column names mapped to canonical keys.
     */
    private const array NL_COLUMNS = [
        'Datum' => 'date',
        'Tijd' => 'time',
        'Product' => 'product',
        'ISIN' => 'isin',
        'Beurs' => 'exchange',
        'Uitvoeringsplaats' => 'venue',
        'Aantal' => 'quantity',
        'Koers' => 'price',
        'Lokale waarde' => 'local_value',
        'Waarde' => 'value',
        'Wisselkoers' => 'exchange_rate',
        'Transactiekosten' => 'transaction_costs',
        'Totaal' => 'total',
        'Order ID' => 'order_id',
    ];

    private const array REQUIRED_CANONICAL = ['date', 'time', 'product', 'isin', 'quantity', 'price'];

    public function brokerId(): BrokerId
    {
        return BrokerId::of(self::BROKER_ID);
    }

    public function supports(string $content, string $filename): bool
    {
        if ($content === '') {
            return false;
        }

        $firstLine = strtok($content, "\n");

        if ($firstLine === false) {
            return false;
        }

        $firstLine = trim($firstLine);

        // English header detection
        if (str_contains($firstLine, 'Date') && str_contains($firstLine, 'Time') && str_contains($firstLine, 'ISIN') && str_contains($firstLine, 'Product')) {
            // Make sure it's NOT an IBKR file (they have "Statement,Header" pattern)
            if (str_contains($firstLine, 'Statement') || str_contains($firstLine, 'Header,')) {
                return false;
            }

            return true;
        }

        // Dutch header detection
        if (str_contains($firstLine, 'Datum') && str_contains($firstLine, 'Tijd') && str_contains($firstLine, 'ISIN') && str_contains($firstLine, 'Product')) {
            return true;
        }

        return false;
    }

    public function parse(string $csvContent): ParseResult
    {
        $transactions = [];
        $errors = [];
        $warnings = [];

        $lines = explode("\n", $csvContent);

        if (count($lines) === 0) {
            return $this->buildResult($transactions, $errors, $warnings);
        }

        $headerLine = trim($lines[0]);
        $columnMapping = $this->resolveColumnMapping($headerLine);

        if ($columnMapping === null) {
            $errors[] = new ParseError(
                lineNumber: 1,
                section: self::SECTION_NAME,
                message: 'Unable to detect Degiro CSV column format (neither EN nor NL headers found)',
            );

            return $this->buildResult($transactions, $errors, $warnings);
        }

        $headers = $this->parseHeaderRow($headerLine);
        $canonicalMap = $this->buildCanonicalMap($headers, $columnMapping);

        $missingRequired = array_diff(self::REQUIRED_CANONICAL, array_values($canonicalMap));

        if ($missingRequired !== []) {
            $errors[] = new ParseError(
                lineNumber: 1,
                section: self::SECTION_NAME,
                message: sprintf('Missing required columns: %s', implode(', ', $missingRequired)),
            );

            return $this->buildResult($transactions, $errors, $warnings);
        }

        for ($i = 1, $lineCount = count($lines); $i < $lineCount; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $lineNumber = $i + 1;
            $fields = str_getcsv($line);
            $mapped = $this->mapFieldsToCanonical($headers, $fields, $canonicalMap);

            try {
                $transactions[] = $this->buildTransaction($mapped, $headers, $fields, $lineNumber);
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $lineNumber,
                    section: self::SECTION_NAME,
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $this->mapFieldsToRaw($headers, $fields)),
                );
            }
        }

        return $this->buildResult($transactions, $errors, $warnings);
    }

    /**
     * Detects whether the header matches EN or NL column set.
     *
     * @return array<string, string>|null Column name -> canonical key mapping, or null if unrecognized
     */
    private function resolveColumnMapping(string $headerLine): ?array
    {
        if (str_contains($headerLine, 'Execution Venue') || (str_contains($headerLine, 'Date') && str_contains($headerLine, 'Time') && str_contains($headerLine, 'Quantity'))) {
            return self::EN_COLUMNS;
        }

        if (str_contains($headerLine, 'Uitvoeringsplaats') || (str_contains($headerLine, 'Datum') && str_contains($headerLine, 'Tijd') && str_contains($headerLine, 'Aantal'))) {
            return self::NL_COLUMNS;
        }

        return null;
    }

    /**
     * Parses header row into individual column names.
     * Degiro CSVs have currency columns interleaved (e.g., "Price,,Local value,").
     * We parse all columns and handle empties.
     *
     * @return list<string>
     */
    private function parseHeaderRow(string $headerLine): array
    {
        return array_map(
            static fn (string|null $v): string => trim((string) $v),
            str_getcsv($headerLine),
        );
    }

    /**
     * Builds mapping: header index -> canonical key (only for recognized columns).
     *
     * @param list<string> $headers
     * @param array<string, string> $columnMapping
     * @return array<int, string>
     */
    private function buildCanonicalMap(array $headers, array $columnMapping): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            if ($header !== '' && isset($columnMapping[$header])) {
                $map[$index] = $columnMapping[$header];
            }
        }

        return $map;
    }

    /**
     * Maps CSV fields to canonical keys using the column mapping.
     *
     * @param list<string> $headers
     * @param list<string|null> $fields
     * @param array<int, string> $canonicalMap
     * @return array<string, string>
     */
    private function mapFieldsToCanonical(array $headers, array $fields, array $canonicalMap): array
    {
        $mapped = [];

        foreach ($canonicalMap as $index => $canonical) {
            $mapped[$canonical] = trim((string) ($fields[$index] ?? ''));
        }

        // Detect currency from neighboring empty-header columns after Price, Local value, Transaction costs, Total
        $mapped['price_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Price', 'Koers');
        $mapped['local_value_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Local value', 'Lokale waarde');
        $mapped['costs_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Transaction costs', 'Transactiekosten');
        $mapped['total_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Total', 'Totaal');
        $mapped['value_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Value', 'Waarde');

        return $mapped;
    }

    /**
     * Degiro places currency codes in unnamed columns immediately after value columns.
     * E.g., headers: ...,Price,,Local value,,... where the empty column holds "USD".
     *
     * @param list<string> $headers
     * @param list<string|null> $fields
     */
    private function detectCurrencyAfterColumn(array $headers, array $fields, string ...$columnNames): string
    {
        foreach ($columnNames as $name) {
            $index = array_search($name, $headers, true);

            if ($index === false) {
                continue;
            }

            $nextIndex = $index + 1;

            if (isset($fields[$nextIndex]) && isset($headers[$nextIndex]) && $headers[$nextIndex] === '') {
                $candidate = trim((string) $fields[$nextIndex]);

                if (preg_match('/^[A-Z]{3}$/', $candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * @param list<string> $headers
     * @param list<string|null> $fields
     * @return array<string, string>
     */
    private function mapFieldsToRaw(array $headers, array $fields): array
    {
        $raw = [];

        foreach ($headers as $index => $header) {
            $key = $header !== '' ? $header : "col_{$index}";
            $raw[$key] = trim((string) ($fields[$index] ?? ''));
        }

        return $raw;
    }

    /**
     * @param array<string, string> $mapped Canonical-keyed row data
     * @param list<string> $headers
     * @param list<string|null> $fields
     */
    private function buildTransaction(array $mapped, array $headers, array $fields, int $lineNumber): NormalizedTransaction
    {
        $quantity = BigDecimal::of($mapped['quantity']);
        $type = $quantity->isNegative() ? TransactionType::SELL : TransactionType::BUY;
        $absQuantity = $quantity->abs();

        $price = $mapped['price'];
        $currencyCode = $this->resolveCurrency($mapped['price_currency'], $mapped['local_value_currency'], $mapped['costs_currency']);

        $commission = $this->parseCommission($mapped['transaction_costs'] ?? '0');
        $commissionCurrency = $this->resolveCurrency($mapped['costs_currency'], $mapped['total_currency'], $mapped['price_currency']);

        $dateTime = $this->parseDateTime($mapped['date'], $mapped['time']);
        $isin = $this->tryParseISIN($mapped['isin']);
        $product = $this->sanitize($mapped['product']);
        $orderId = $this->sanitize($mapped['order_id'] ?? '');

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $product,
            type: $type,
            date: $dateTime,
            quantity: $absQuantity,
            pricePerUnit: Money::of($price, $currencyCode),
            commission: Money::of($commission, $commissionCurrency),
            broker: $this->brokerId(),
            description: $this->sanitize(
                sprintf('Degiro %s: %s %s @ %s %s', $type->value, $absQuantity, $product, $price, $currencyCode->value),
            ),
            rawData: array_map($this->sanitize(...), $this->mapFieldsToRaw($headers, $fields)),
        );
    }

    private function parseDateTime(string $date, string $time): \DateTimeImmutable
    {
        $combined = trim("{$date} {$time}");

        // DD-MM-YYYY HH:MM
        $parsed = \DateTimeImmutable::createFromFormat('d-m-Y H:i', $combined);

        if ($parsed !== false) {
            return $parsed;
        }

        // Fallback: DD-MM-YYYY only
        $parsed = \DateTimeImmutable::createFromFormat('d-m-Y', $date);

        if ($parsed !== false) {
            return $parsed;
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse Degiro date/time: "%s"', $combined));
    }

    private function tryParseISIN(string $value): ?ISIN
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return ISIN::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Transaction costs in Degiro are negative (cost) — we take absolute value.
     */
    private function parseCommission(string $value): string
    {
        if ($value === '') {
            return '0';
        }

        return BigDecimal::of($value)->abs()->__toString();
    }

    /**
     * Resolves currency from multiple candidate columns (first non-empty wins).
     */
    private function resolveCurrency(string ...$candidates): CurrencyCode
    {
        foreach ($candidates as $code) {
            $code = strtoupper(trim($code));

            if ($code === '') {
                continue;
            }

            $currency = CurrencyCode::tryFrom($code);

            if ($currency !== null) {
                return $currency;
            }
        }

        return CurrencyCode::EUR; // Degiro default
    }

    /**
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     */
    private function buildResult(array $transactions, array $errors, array $warnings): ParseResult
    {
        $dateFrom = null;
        $dateTo = null;

        foreach ($transactions as $tx) {
            if ($dateFrom === null || $tx->date < $dateFrom) {
                $dateFrom = $tx->date;
            }

            if ($dateTo === null || $tx->date > $dateTo) {
                $dateTo = $tx->date;
            }
        }

        return new ParseResult(
            transactions: $transactions,
            errors: $errors,
            warnings: $warnings,
            metadata: new ParseMetadata(
                broker: $this->brokerId(),
                totalTransactions: count($transactions),
                totalErrors: count($errors),
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                sectionsFound: $transactions !== [] ? [self::SECTION_NAME] : [],
            ),
        );
    }
}
