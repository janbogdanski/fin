<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Degiro;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\CsvSanitizer;
use App\BrokerImport\Infrastructure\Adapter\ParseResultBuilder;
use App\Shared\Domain\PolishTimezone;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

/**
 * Parses Degiro "Account Statement" CSV export — extracts dividends and withholding tax.
 *
 * Degiro separates dividends from trades. This adapter handles the Account Statement CSV
 * where dividend-related rows are identified by Description containing "Dividend" or "Dividendbelasting".
 *
 * Columns: Date, Time, Product, ISIN, Description, FX, Change, [currency], Balance, [currency], Order ID
 */
final readonly class DegiroAccountStatementAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;
    use ParseResultBuilder;

    private const string BROKER_ID = 'degiro';

    private const string SECTION_NAME = 'Account Statement';

    private const array EN_COLUMNS = [
        'Date' => 'date',
        'Time' => 'time',
        'Product' => 'product',
        'ISIN' => 'isin',
        'Description' => 'description',
        'FX' => 'fx',
        'Change' => 'change',
        'Balance' => 'balance',
        'Order ID' => 'order_id',
    ];

    private const array REQUIRED_CANONICAL = ['date', 'description', 'change'];

    /**
     * Keywords that identify dividend rows (case-insensitive).
     */
    private const array DIVIDEND_KEYWORDS = ['dividend'];

    /**
     * Keywords that identify withholding tax rows (case-insensitive).
     */
    private const array WHT_KEYWORDS = ['dividendbelasting', 'dividend tax', 'withholding tax'];

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

        // Account Statement has Description, Change, Balance — but NOT Quantity/Price columns
        $hasAccountColumns = str_contains($firstLine, 'Description') && str_contains($firstLine, 'Change') && str_contains($firstLine, 'Balance');
        $lacksTradeColumns = ! str_contains($firstLine, 'Quantity') && ! str_contains($firstLine, 'Aantal');
        $isNotIbkr = ! str_contains($firstLine, 'Statement,') && ! str_contains($firstLine, 'Header,');

        return $hasAccountColumns && $lacksTradeColumns && $isNotIbkr;
    }

    public function parse(string $csvContent): ParseResult
    {
        $transactions = [];
        $errors = [];
        $warnings = [];

        // TODO: P2-028 — replace explode() with streaming (fgets/SplFileObject) to reduce memory footprint
        $lines = explode("\n", $csvContent);

        if (count($lines) === 0) {
            return $this->buildParseResult($transactions, $errors, $warnings, []);
        }

        $headers = $this->parseHeaderRow(trim($lines[0]));
        $canonicalMap = $this->buildCanonicalMap($headers);

        $missingRequired = array_diff(self::REQUIRED_CANONICAL, array_values($canonicalMap));

        if ($missingRequired !== []) {
            $errors[] = new ParseError(
                lineNumber: 1,
                section: self::SECTION_NAME,
                message: sprintf('Missing required columns: %s', implode(', ', $missingRequired)),
            );

            return $this->buildParseResult($transactions, $errors, $warnings, []);
        }

        for ($i = 1, $lineCount = count($lines); $i < $lineCount; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $lineNumber = $i + 1;
            $fields = str_getcsv($line, ',', '"', '');
            $mapped = $this->mapFieldsToCanonical($headers, $fields, $canonicalMap);

            $description = strtolower($mapped['description'] ?? '');
            $type = $this->detectTransactionType($description);

            if ($type === null) {
                // Not a dividend-related row — skip silently
                continue;
            }

            try {
                $transactions[] = $this->buildTransaction($mapped, $headers, $fields, $type, $lineNumber);
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $lineNumber,
                    section: self::SECTION_NAME,
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $this->mapFieldsToRaw($headers, $fields)),
                );
            }
        }

        return $this->buildParseResult($transactions, $errors, $warnings, $transactions !== [] ? [self::SECTION_NAME] : []);
    }

    private function detectTransactionType(string $descriptionLower): ?TransactionType
    {
        // WHT keywords must be checked BEFORE generic dividend keywords
        foreach (self::WHT_KEYWORDS as $keyword) {
            if (str_contains($descriptionLower, $keyword)) {
                return TransactionType::WITHHOLDING_TAX;
            }
        }

        foreach (self::DIVIDEND_KEYWORDS as $keyword) {
            if (str_contains($descriptionLower, $keyword)) {
                return TransactionType::DIVIDEND;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseHeaderRow(string $headerLine): array
    {
        return array_map(
            static fn (string|null $v): string => trim((string) $v),
            str_getcsv($headerLine, ',', '"', ''),
        );
    }

    /**
     * @param list<string> $headers
     * @return array<int, string>
     */
    private function buildCanonicalMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            if ($header !== '' && isset(self::EN_COLUMNS[$header])) {
                $map[$index] = self::EN_COLUMNS[$header];
            }
        }

        return $map;
    }

    /**
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

        // Detect currency from unnamed column after Change
        $mapped['change_currency'] = $this->detectCurrencyAfterColumn($headers, $fields, 'Change');

        return $mapped;
    }

    /**
     * @param list<string> $headers
     * @param list<string|null> $fields
     */
    private function detectCurrencyAfterColumn(array $headers, array $fields, string $columnName): string
    {
        $index = array_search($columnName, $headers, true);

        if ($index === false) {
            return '';
        }

        $nextIndex = $index + 1;

        if (isset($fields[$nextIndex]) && isset($headers[$nextIndex]) && $headers[$nextIndex] === '') {
            $candidate = trim((string) $fields[$nextIndex]);

            if (preg_match('/^[A-Z]{3}$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $mapped
     * @param list<string> $headers
     * @param list<string|null> $fields
     */
    private function buildTransaction(
        array $mapped,
        array $headers,
        array $fields,
        TransactionType $type,
        int $lineNumber,
    ): NormalizedTransaction {
        $amount = BigDecimal::of($mapped['change'])->abs();
        $currencyCode = $this->resolveCurrency($mapped['change_currency'] ?? '');
        $dateTime = $this->parseDateTime($mapped['date'] ?? '', $mapped['time'] ?? '');
        $isin = $this->tryParseISIN($mapped['isin'] ?? '');
        $product = $this->sanitize($mapped['product'] ?? '');

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $product,
            type: $type,
            date: $dateTime,
            quantity: BigDecimal::one(),
            pricePerUnit: Money::of($amount->__toString(), $currencyCode),
            commission: Money::zero($currencyCode),
            broker: $this->brokerId(),
            description: $this->sanitize(
                sprintf('Degiro %s: %s (%s)', $type->value, $product, $mapped['description'] ?? ''),
            ),
            rawData: array_map($this->sanitize(...), $this->mapFieldsToRaw($headers, $fields)),
        );
    }

    private function parseDateTime(string $date, string $time): \DateTimeImmutable
    {
        $combined = trim("{$date} {$time}");
        $tz = PolishTimezone::get();

        $parsed = \DateTimeImmutable::createFromFormat('d-m-Y H:i', $combined, $tz);

        if ($parsed !== false) {
            return $parsed;
        }

        $parsed = \DateTimeImmutable::createFromFormat('d-m-Y', $date, $tz);

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

    private function resolveCurrency(string $code): CurrencyCode
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return CurrencyCode::EUR;
        }

        return CurrencyCode::tryFrom($code) ?? CurrencyCode::EUR;
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
}
