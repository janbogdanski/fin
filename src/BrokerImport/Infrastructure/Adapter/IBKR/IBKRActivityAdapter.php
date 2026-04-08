<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\IBKR;

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
 * Parses Interactive Brokers Activity Statement CSV export.
 *
 * IBKR CSV format uses section-based layout:
 *   SectionName,Header,Col1,Col2,...
 *   SectionName,Data,Val1,Val2,...
 *   SectionName,Total,...
 *
 * This adapter handles: Trades, Dividends, Withholding Tax sections.
 */
final readonly class IBKRActivityAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;

    private const string BROKER_ID = 'ibkr';

    public function brokerId(): BrokerId
    {
        return BrokerId::of(self::BROKER_ID);
    }

    public function priority(): int
    {
        return 100;
    }

    public function supports(string $content, string $filename): bool
    {
        if ($content === '') {
            return false;
        }

        $content = $this->stripBom($content);
        $firstChunk = substr($content, 0, 2000);

        return str_contains($firstChunk, 'Interactive Brokers')
            || str_contains($firstChunk, 'Statement,Header');
    }

    public function parse(string $csvContent, string $filename = ''): ParseResult
    {
        $csvContent = $this->stripBom($csvContent);
        $sections = $this->extractSections($csvContent);
        $transactions = [];
        $errors = [];
        $warnings = [];
        $sectionsFound = [];

        if (isset($sections['Trades'])) {
            $sectionsFound[] = 'Trades';
            $this->parseTrades($sections['Trades'], $transactions, $errors, $warnings);
        }

        if (isset($sections['Dividends'])) {
            $sectionsFound[] = 'Dividends';
            $this->parseDividends($sections['Dividends'], $transactions, $errors, $warnings);
        }

        if (isset($sections['Withholding Tax'])) {
            $sectionsFound[] = 'Withholding Tax';
            $this->parseWithholdingTax($sections['Withholding Tax'], $transactions, $errors, $warnings);
        }

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
                sectionsFound: $sectionsFound,
            ),
        );
    }

    /**
     * Extracts CSV into sections grouped by section name.
     * Each section contains: headers (from Header row) and data rows.
     *
     * @return array<string, array{headers: list<string>, rows: list<array{line: int, fields: list<string>}>}>
     */
    private function extractSections(string $csvContent): array
    {
        $sections = [];
        // TODO: P2-028 — replace explode() with streaming (fgets/SplFileObject) to reduce memory footprint
        $lines = explode("\n", $csvContent);

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line, ',', '"', '');

            if (count($fields) < 3) {
                continue;
            }

            $sectionName = \trim((string) $fields[0]);
            $rowType = \trim((string) $fields[1]);

            if ($rowType === 'Header') {
                $sections[$sectionName] = [
                    'headers' => \array_map(fn ($v) => \trim((string) $v), \array_slice($fields, 2)),
                    'rows' => [],
                ];
            } elseif ($rowType === 'Data' && isset($sections[$sectionName])) {
                $sections[$sectionName]['rows'][] = [
                    'line' => $lineIndex + 1,
                    'fields' => \array_map(fn ($v) => \trim((string) $v), \array_slice($fields, 2)),
                ];
            }
        }

        return $sections;
    }

    /**
     * @param array{headers: list<string>, rows: list<array{line: int, fields: list<string>}>} $section
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     */
    private function parseTrades(
        array $section,
        array &$transactions,
        array &$errors,
        array &$warnings,
    ): void {
        $headers = $section['headers'];

        $requiredColumns = ['Symbol', 'Date/Time', 'Quantity', 'T. Price', 'Comm/Fee', 'Currency'];
        $missing = array_diff($requiredColumns, $headers);

        if ($missing !== []) {
            $errors[] = new ParseError(
                lineNumber: 0,
                section: 'Trades',
                message: sprintf('Missing required columns: %s', implode(', ', $missing)),
            );

            return;
        }

        foreach ($section['rows'] as $row) {
            $mapped = $this->mapFieldsToHeaders($headers, $row['fields']);

            // Skip subtotal/total rows
            $dataDiscriminator = $mapped['DataDiscriminator'] ?? '';

            if (in_array($dataDiscriminator, ['SubTotal', 'Total'], true)) {
                continue;
            }

            // Skip non-stock asset categories
            $assetCategory = $mapped['Asset Category'] ?? 'Stocks';

            if ($assetCategory !== '' && $assetCategory !== 'Stocks') {
                $warnings[] = new ParseWarning(
                    lineNumber: $row['line'],
                    section: 'Trades',
                    message: sprintf('Skipping non-stock asset category: %s', $assetCategory),
                );

                continue;
            }

            try {
                $transactions[] = $this->buildTradeTransaction($mapped, $row);
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $row['line'],
                    section: 'Trades',
                    message: $e->getMessage(),
                    rawData: $mapped,
                );
            }
        }
    }

    /**
     * @param array<string, string> $mapped
     * @param array{line: int, fields: list<string>} $row
     */
    private function buildTradeTransaction(array $mapped, array $row): NormalizedTransaction
    {
        $symbol = $this->sanitize($mapped['Symbol'] ?? '');
        $currencyCode = $this->resolveCurrency($mapped['Currency'] ?? '');
        $quantity = BigDecimal::of($mapped['Quantity'] ?? '0');
        $type = $quantity->isNegative() ? TransactionType::SELL : TransactionType::BUY;
        $quantity = $quantity->abs();
        $price = $mapped['T. Price'] ?? '0';
        $commission = $mapped['Comm/Fee'] ?? '0';
        $dateTime = $this->parseDateTime($mapped['Date/Time'] ?? '');
        $isin = $this->tryExtractISIN($mapped);

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $symbol,
            type: $type,
            date: $dateTime,
            quantity: $quantity,
            pricePerUnit: Money::of($price, $currencyCode),
            commission: Money::of(BigDecimal::of($commission)->abs()->__toString(), $currencyCode),
            broker: $this->brokerId(),
            description: $this->sanitize(
                sprintf('IBKR Trade: %s %s %s @ %s', $type->value, $quantity, $symbol, $price),
            ),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * @param array{headers: list<string>, rows: list<array{line: int, fields: list<string>}>} $section
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     */
    private function parseDividends(
        array $section,
        array &$transactions,
        array &$errors,
        array &$warnings,
    ): void {
        $headers = $section['headers'];

        $requiredColumns = ['Date', 'Description', 'Amount', 'Currency'];
        $missing = array_diff($requiredColumns, $headers);

        if ($missing !== []) {
            $errors[] = new ParseError(
                lineNumber: 0,
                section: 'Dividends',
                message: sprintf('Missing required columns: %s', implode(', ', $missing)),
            );

            return;
        }

        foreach ($section['rows'] as $row) {
            $mapped = $this->mapFieldsToHeaders($headers, $row['fields']);

            try {
                $transactions[] = $this->buildDividendTransaction($mapped, $row, TransactionType::DIVIDEND);
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $row['line'],
                    section: 'Dividends',
                    message: $e->getMessage(),
                    rawData: $mapped,
                );
            }
        }
    }

    /**
     * @param array{headers: list<string>, rows: list<array{line: int, fields: list<string>}>} $section
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param list<ParseWarning> $warnings
     */
    private function parseWithholdingTax(
        array $section,
        array &$transactions,
        array &$errors,
        array &$warnings,
    ): void {
        $headers = $section['headers'];

        $requiredColumns = ['Date', 'Description', 'Amount', 'Currency'];
        $missing = array_diff($requiredColumns, $headers);

        if ($missing !== []) {
            $errors[] = new ParseError(
                lineNumber: 0,
                section: 'Withholding Tax',
                message: sprintf('Missing required columns: %s', implode(', ', $missing)),
            );

            return;
        }

        foreach ($section['rows'] as $row) {
            $mapped = $this->mapFieldsToHeaders($headers, $row['fields']);

            try {
                $transactions[] = $this->buildDividendTransaction($mapped, $row, TransactionType::WITHHOLDING_TAX);
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $row['line'],
                    section: 'Withholding Tax',
                    message: $e->getMessage(),
                    rawData: $mapped,
                );
            }
        }
    }

    /**
     * @param array<string, string> $mapped
     * @param array{line: int, fields: list<string>} $row
     */
    private function buildDividendTransaction(
        array $mapped,
        array $row,
        TransactionType $type,
    ): NormalizedTransaction {
        $description = $this->sanitize($mapped['Description'] ?? '');
        $currencyCode = $this->resolveCurrency($mapped['Currency'] ?? '');
        $amount = $mapped['Amount'] ?? '0';
        $date = $this->parseDate($mapped['Date'] ?? '');
        $symbol = $this->extractSymbolFromDescription($description);
        $isin = $this->tryExtractISINFromDescription($description);

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $symbol,
            type: $type,
            date: $date,
            quantity: BigDecimal::one(),
            pricePerUnit: Money::of(BigDecimal::of($amount)->abs()->__toString(), $currencyCode),
            commission: Money::zero($currencyCode),
            broker: $this->brokerId(),
            description: $this->sanitize($description),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * @param list<string> $headers
     * @param list<string> $fields
     * @return array<string, string>
     */
    private function mapFieldsToHeaders(array $headers, array $fields): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $mapped[$header] = $fields[$index] ?? '';
        }

        return $mapped;
    }

    private function parseDateTime(string $value): \DateTimeImmutable
    {
        // IBKR uses formats like "2024-01-15, 10:30:00" or "2024-01-15;10:30:00" or "20240115;103000"
        // Flex queries may also include milliseconds: "2024-01-15, 10:30:00.123" or "20240115;103000123"
        $value = str_replace([', ', ';'], [' ', ' '], $value);
        $value = trim($value);

        // Try common IBKR datetime formats (most specific first)
        $formats = ['Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'Ymd Hisv', 'Ymd His', 'Y-m-d'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse date/time: "%s"', $value));
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $formats = ['Y-m-d', 'Ymd', 'd/m/Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($value));

            if ($date !== false) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse date: "%s"', $value));
    }

    private function resolveCurrency(string $code): CurrencyCode
    {
        $code = strtoupper(trim($code));
        $currency = CurrencyCode::tryFrom($code);

        if ($currency === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported currency code: "%s"', $code));
        }

        return $currency;
    }

    /**
     * Attempts to extract ISIN from mapped fields (e.g., ISIN column or Code column).
     *
     * @param array<string, string> $mapped
     */
    private function tryExtractISIN(array $mapped): ?ISIN
    {
        $candidates = [$mapped['ISIN'] ?? '', $mapped['Code'] ?? ''];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            try {
                return ISIN::fromString($candidate);
            } catch (\InvalidArgumentException) {
                // Not a valid ISIN, continue
            }
        }

        return null;
    }

    private function tryExtractISINFromDescription(string $description): ?ISIN
    {
        // IBKR dividend descriptions sometimes contain ISIN, e.g. "AAPL(US0378331005) Cash Dividend..."
        if (preg_match('/\(([A-Z]{2}[A-Z0-9]{9}[0-9])\)/', $description, $matches)) {
            try {
                return ISIN::fromString($matches[1]);
            } catch (\InvalidArgumentException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Extracts ticker symbol from IBKR dividend description.
     * Format: "AAPL(US0378331005) Cash Dividend USD 0.25 per Share..."
     */
    private function extractSymbolFromDescription(string $description): string
    {
        if (preg_match('/^([A-Z0-9.]+)\s*\(/', $description, $matches)) {
            return $matches[1];
        }

        // Fallback: first word
        $parts = explode(' ', trim($description));

        return $parts[0] ?? '';
    }
}
