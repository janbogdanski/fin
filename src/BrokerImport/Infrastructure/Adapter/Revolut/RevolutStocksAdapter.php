<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Revolut;

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
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

/**
 * Parses Revolut Stocks "Trading Activity Statement" CSV export.
 *
 * Supports two header variants:
 *   - Legacy:  Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate
 *   - Newer:   Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission
 *
 * Revolut does NOT provide ISIN codes — only ticker symbols.
 * Uses TickerToISINMap for static resolution; unresolved tickers get pseudo-ISIN.
 */
final readonly class RevolutStocksAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;

    private const string BROKER_ID = 'revolut';

    /**
     * @var list<list<string>>
     */
    private const array SUPPORTED_HEADER_SETS = [
        ['Date', 'Ticker', 'Type'],
        ['Date', 'Symbol', 'Type'],
    ];

    /**
     * @var array<string, TransactionType>
     */
    private const array TYPE_MAP = [
        'BUY' => TransactionType::BUY,
        'SELL' => TransactionType::SELL,
        'DIVIDEND' => TransactionType::DIVIDEND,
        'CUSTODY FEE' => TransactionType::FEE,
        'STOCK SPLIT' => TransactionType::CORPORATE_ACTION,
    ];

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

        $headers = array_map(
            static fn (string|null $h): string => \trim((string) $h),
            str_getcsv($firstLine, ',', '"', ''),
        );

        foreach (self::SUPPORTED_HEADER_SETS as $requiredHeaders) {
            if (array_intersect($requiredHeaders, $headers) === $requiredHeaders) {
                return true;
            }
        }

        return false;
    }

    public function parse(string $csvContent): ParseResult
    {
        // TODO: P2-028 — replace explode() with streaming (fgets/SplFileObject) to reduce memory footprint
        $lines = explode("\n", $csvContent);
        $transactions = [];
        $errors = [];
        $warnings = [];
        /** @var array<string, true> $unresolvedTickers tracks unique tickers without ISIN */
        $unresolvedTickers = [];

        if ($lines === [] || trim($lines[0]) === '') {
            return $this->buildResult($transactions, $errors, $warnings);
        }

        $headers = array_map(
            static fn (string|null $h): string => \trim((string) $h),
            str_getcsv(trim($lines[0]), ',', '"', ''),
        );

        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $fields = array_map(fn ($v) => (string) $v, str_getcsv($line, ',', '"', ''));
            $mapped = $this->mapFieldsToHeaders($headers, $fields);
            $lineNumber = $i + 1;

            try {
                $tx = $this->buildTransaction($mapped, $lineNumber, $warnings, $unresolvedTickers);

                if ($tx !== null) {
                    $transactions[] = $tx;
                }
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $lineNumber,
                    section: 'Trades',
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $mapped),
                );
            }
        }

        // P1-012: Emit a single summary warning listing all unresolved tickers
        if ($unresolvedTickers !== []) {
            $tickerList = implode(', ', array_keys($unresolvedTickers));
            $warnings[] = new ParseWarning(
                lineNumber: 0,
                section: 'Trades',
                message: sprintf(
                    'ISIN not available for %d ticker(s): %s. Using pseudo-ISIN (TICKER:XXX) for grouping.',
                    count($unresolvedTickers),
                    $tickerList,
                ),
            );
        }

        return $this->buildResult($transactions, $errors, $warnings);
    }

    /**
     * @param array<string, string> $mapped
     * @param list<ParseWarning> $warnings
     * @param array<string, true> $unresolvedTickers collects unique tickers without ISIN mapping
     */
    private function buildTransaction(
        array $mapped,
        int $lineNumber,
        array &$warnings,
        array &$unresolvedTickers,
    ): ?NormalizedTransaction {
        $rawType = strtoupper(trim($mapped['Type'] ?? ''));
        $type = self::TYPE_MAP[$rawType] ?? null;

        if ($type === null) {
            $warnings[] = new ParseWarning(
                lineNumber: $lineNumber,
                section: 'Trades',
                message: sprintf('Unknown transaction type: "%s", skipping row', $rawType),
            );

            return null;
        }

        $symbol = $this->sanitize($mapped['Ticker'] ?? $mapped['Symbol'] ?? '');
        $currencyCode = $this->resolveCurrency($mapped['Currency'] ?? '');
        $quantity = BigDecimal::of($mapped['Quantity'] ?? '0')->abs();
        $price = $mapped['Price per share'] ?? $mapped['Price'] ?? '0';
        $commission = $this->resolveCommission($mapped, $currencyCode);
        $date = $this->parseDate($mapped['Date'] ?? '');

        // P1-046: Resolve ISIN from static map, fall back to pseudo-ISIN
        $isin = TickerToISINMap::resolve($symbol);

        if ($isin === null) {
            $unresolvedTickers[$symbol] = true;
        }

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: $isin === null ? sprintf('TICKER:%s', $symbol) : $symbol,
            type: $type,
            date: $date,
            quantity: $quantity,
            pricePerUnit: Money::of($price, $currencyCode),
            commission: $commission,
            broker: $this->brokerId(),
            description: $this->sanitize(
                sprintf('Revolut: %s %s %s @ %s', $type->value, $quantity, $symbol, $price),
            ),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * @param array<string, string> $mapped
     */
    private function resolveCommission(array $mapped, CurrencyCode $currency): Money
    {
        $commission = trim($mapped['Commission'] ?? '');

        if ($commission !== '' && is_numeric($commission)) {
            return Money::of(BigDecimal::of($commission)->abs()->__toString(), $currency);
        }

        return Money::zero($currency);
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse date: "%s"', $value));
    }

    private function resolveCurrency(string $code): CurrencyCode
    {
        return CurrencyCode::from(strtoupper(trim($code)));
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
            $mapped[$header] = trim((string) ($fields[$index] ?? ''));
        }

        return $mapped;
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
                sectionsFound: ['Trades'],
            ),
        );
    }
}
