<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\XTB;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseError;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\ParseWarning;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Infrastructure\Adapter\CsvSanitizer;
use App\BrokerImport\Infrastructure\Adapter\ParseResultBuilder;
use App\BrokerImport\Infrastructure\Adapter\Spreadsheet\XlsxWorkbookReader;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

final readonly class XTBStatementAdapter implements BrokerAdapterInterface
{
    use CsvSanitizer;
    use ParseResultBuilder;

    private const string BROKER_ID = 'xtb';

    private const string CLOSED_POSITIONS_SHEET = 'Closed Positions';

    private const string CASH_OPERATIONS_SHEET = 'Cash Operations';

    /**
     * @var list<string>
     */
    private const array CLOSED_POSITION_REQUIRED_HEADERS = [
        'Instrument',
        'Ticker',
        'Type',
        'Volume',
        'Open Price',
        'Open Time (UTC)',
        'Close Price',
        'Close Time (UTC)',
        'Category',
    ];

    /**
     * @var list<string>
     */
    private const array CASH_OPERATION_REQUIRED_HEADERS = [
        'Type',
        'Ticker',
        'Instrument',
        'Time',
        'Amount',
        'Comment',
    ];

    /**
     * @var array<string, TransactionType>
     */
    private const array CASH_OPERATION_TYPE_MAP = [
        'dividend' => TransactionType::DIVIDEND,
        'withholding tax' => TransactionType::WITHHOLDING_TAX,
    ];

    private const string TRADE_COMMENT_PATTERN = '/^(OPEN|CLOSE)\s+BUY\s+([0-9.]+(?:\/[0-9.]+)?)\s+@\s+([0-9.]+)$/';

    /**
     * @var array<string, true>
     */
    private const array SUPPORTED_CATEGORIES = [
        'STOCK' => true,
        'ETF' => true,
        'ETC' => true,
    ];

    public function __construct(
        private XlsxWorkbookReader $workbookReader,
    ) {
    }

    public function brokerId(): BrokerId
    {
        return BrokerId::of(self::BROKER_ID);
    }

    public function priority(): int
    {
        return 90;
    }

    public function supports(string $content, string $filename): bool
    {
        if ($content === '' || ! str_starts_with($content, 'PK')) {
            return false;
        }

        try {
            $sheets = $this->workbookReader->read($content);
        } catch (\Throwable) {
            return false;
        }

        $hasClosedPositions = isset($sheets[self::CLOSED_POSITIONS_SHEET])
            && $this->findHeaderRowIndex($sheets[self::CLOSED_POSITIONS_SHEET], self::CLOSED_POSITION_REQUIRED_HEADERS) !== null;
        $hasCashOperations = isset($sheets[self::CASH_OPERATIONS_SHEET])
            && $this->findHeaderRowIndex($sheets[self::CASH_OPERATIONS_SHEET], self::CASH_OPERATION_REQUIRED_HEADERS) !== null;

        return $hasClosedPositions || $hasCashOperations;
    }

    public function parse(string $fileContent, string $filename = ''): ParseResult
    {
        $sheets = $this->workbookReader->read($fileContent);
        $transactions = [];
        $errors = [];
        $warnings = [];
        $skippedCategories = [];
        $skippedCashOperationTypes = [];
        $unsupportedPositionTypes = [];
        $sectionsFound = [];
        $accountCurrency = $this->resolveAccountCurrency($filename);
        $cashTradeCount = 0;
        $closedPositionRows = $sheets[self::CLOSED_POSITIONS_SHEET] ?? null;
        $cashOperationRows = $sheets[self::CASH_OPERATIONS_SHEET] ?? null;
        $closedPositionHeaderRowIndex = $closedPositionRows === null
            ? null
            : $this->findHeaderRowIndex($closedPositionRows, self::CLOSED_POSITION_REQUIRED_HEADERS);
        $cashOperationHeaderRowIndex = $cashOperationRows === null
            ? null
            : $this->findHeaderRowIndex($cashOperationRows, self::CASH_OPERATION_REQUIRED_HEADERS);

        if ($closedPositionHeaderRowIndex !== null) {
            $sectionsFound[] = self::CLOSED_POSITIONS_SHEET;
        } elseif ($closedPositionRows !== null) {
            $errors[] = new ParseError(
                lineNumber: 0,
                section: self::CLOSED_POSITIONS_SHEET,
                message: 'Missing required XTB closed-position columns.',
            );
        }

        if ($cashOperationHeaderRowIndex !== null) {
            $sectionsFound[] = self::CASH_OPERATIONS_SHEET;
        } elseif ($cashOperationRows !== null) {
            $errors[] = new ParseError(
                lineNumber: 0,
                section: self::CASH_OPERATIONS_SHEET,
                message: 'Missing required XTB cash-operation columns.',
            );
        }

        if ($cashOperationRows !== null && $cashOperationHeaderRowIndex !== null) {
            $this->parseCashOperations(
                $cashOperationRows,
                $cashOperationHeaderRowIndex,
                $transactions,
                $errors,
                $skippedCashOperationTypes,
                $accountCurrency,
                $cashTradeCount,
            );
        }

        if ($cashTradeCount === 0 && $closedPositionRows !== null && $closedPositionHeaderRowIndex !== null) {
            $this->parseClosedPositions(
                $closedPositionRows,
                $closedPositionHeaderRowIndex,
                $transactions,
                $errors,
                $skippedCategories,
                $unsupportedPositionTypes,
                $accountCurrency,
            );
        }

        if ($cashTradeCount === 0 && $skippedCategories !== []) {
            $warnings[] = new ParseWarning(
                lineNumber: 0,
                section: self::CLOSED_POSITIONS_SHEET,
                message: sprintf(
                    'Skipped unsupported XTB categories: %s',
                    implode(', ', array_keys($skippedCategories)),
                ),
            );
        }

        if ($cashTradeCount === 0 && $unsupportedPositionTypes !== []) {
            $warnings[] = new ParseWarning(
                lineNumber: 0,
                section: self::CLOSED_POSITIONS_SHEET,
                message: sprintf(
                    'Skipped unsupported XTB position types: %s',
                    implode(', ', array_keys($unsupportedPositionTypes)),
                ),
            );
        }

        if ($skippedCashOperationTypes !== []) {
            $warnings[] = new ParseWarning(
                lineNumber: 0,
                section: self::CASH_OPERATIONS_SHEET,
                message: sprintf(
                    'Skipped cash operation types that are not imported yet: %s',
                    implode(', ', array_keys($skippedCashOperationTypes)),
                ),
            );
        }

        return $this->buildParseResult(
            $transactions,
            $errors,
            $warnings,
            $sectionsFound,
        );
    }

    /**
     * @param list<list<string>> $rows
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param array<string, true> $skippedCategories
     * @param array<string, true> $unsupportedPositionTypes
     */
    private function parseClosedPositions(
        array $rows,
        int $headerRowIndex,
        array &$transactions,
        array &$errors,
        array &$skippedCategories,
        array &$unsupportedPositionTypes,
        ?CurrencyCode $accountCurrency,
    ): void {
        $headers = $this->normalizeHeaders($rows[$headerRowIndex]);

        for ($rowIndex = $headerRowIndex + 1, $count = count($rows); $rowIndex < $count; $rowIndex++) {
            $row = $rows[$rowIndex];

            if (! $this->isDataRow($row)) {
                continue;
            }

            $mapped = $this->mapRowToHeaders($headers, $row);
            $instrument = trim($mapped['Instrument'] ?? '');

            if (in_array($instrument, ['Profit/loss', 'Total'], true)) {
                continue;
            }

            $category = strtoupper(trim($mapped['Category'] ?? ''));

            if (! isset(self::SUPPORTED_CATEGORIES[$category])) {
                $skippedCategories[$category === '' ? '[empty]' : $category] = true;

                continue;
            }

            $positionType = strtoupper(trim($mapped['Type'] ?? ''));

            if ($positionType !== 'BUY') {
                $unsupportedPositionTypes[$positionType === '' ? '[empty]' : $positionType] = true;

                continue;
            }

            try {
                foreach ($this->buildClosedPositionTransactions($mapped, $rowIndex + 1, $accountCurrency) as $transaction) {
                    $transactions[] = $transaction;
                }
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $rowIndex + 1,
                    section: self::CLOSED_POSITIONS_SHEET,
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $mapped),
                );
            }
        }
    }

    /**
     * @param list<list<string>> $rows
     * @param list<NormalizedTransaction> $transactions
     * @param list<ParseError> $errors
     * @param array<string, true> $skippedCashOperationTypes
     */
    private function parseCashOperations(
        array $rows,
        int $headerRowIndex,
        array &$transactions,
        array &$errors,
        array &$skippedCashOperationTypes,
        ?CurrencyCode $accountCurrency,
        int &$cashTradeCount,
    ): void {
        $headers = $this->normalizeHeaders($rows[$headerRowIndex]);

        for ($rowIndex = $headerRowIndex + 1, $count = count($rows); $rowIndex < $count; $rowIndex++) {
            $row = $rows[$rowIndex];

            if (! $this->isDataRow($row)) {
                continue;
            }

            $mapped = $this->mapRowToHeaders($headers, $row);
            $rawType = trim($mapped['Type'] ?? '');

            if ($rawType === 'Total') {
                continue;
            }

            $normalizedType = strtolower($rawType);

            if ($normalizedType === 'stock purchase' || $normalizedType === 'stock sell') {
                try {
                    $transactions[] = $this->buildTradeFromCashOperation(
                        $mapped,
                        $rowIndex + 1,
                        $normalizedType === 'stock purchase' ? TransactionType::BUY : TransactionType::SELL,
                        $accountCurrency,
                    );
                    $cashTradeCount++;
                } catch (\Throwable $e) {
                    $errors[] = new ParseError(
                        lineNumber: $rowIndex + 1,
                        section: self::CASH_OPERATIONS_SHEET,
                        message: $e->getMessage(),
                        rawData: array_map($this->sanitize(...), $mapped),
                    );
                }

                continue;
            }

            $transactionType = self::CASH_OPERATION_TYPE_MAP[$normalizedType] ?? null;

            if ($transactionType === null) {
                $skippedCashOperationTypes[$rawType === '' ? '[empty]' : $rawType] = true;

                continue;
            }

            try {
                $transactions[] = $this->buildCashOperationTransaction(
                    $mapped,
                    $rowIndex + 1,
                    $transactionType,
                    $accountCurrency,
                );
            } catch (\Throwable $e) {
                $errors[] = new ParseError(
                    lineNumber: $rowIndex + 1,
                    section: self::CASH_OPERATIONS_SHEET,
                    message: $e->getMessage(),
                    rawData: array_map($this->sanitize(...), $mapped),
                );
            }
        }
    }

    /**
     * @param array<string, string> $mapped
     * @return list<NormalizedTransaction>
     */
    private function buildClosedPositionTransactions(
        array $mapped,
        int $lineNumber,
        ?CurrencyCode $accountCurrency,
    ): array {
        $currency = $accountCurrency ?? throw new \InvalidArgumentException(sprintf(
            'Unable to determine XTB account currency for closed position on line %d.',
            $lineNumber,
        ));
        $symbol = $this->resolveSymbol($mapped);
        $quantity = BigDecimal::of($mapped['Volume'] ?? '0')->abs();
        $openDate = $this->parseExcelDateTime($mapped['Open Time (UTC)'] ?? '', $lineNumber, 'Open Time (UTC)');
        $closeDate = $this->parseExcelDateTime($mapped['Close Time (UTC)'] ?? '', $lineNumber, 'Close Time (UTC)');
        $openPrice = $this->parseDecimal($mapped['Open Price'] ?? '', $lineNumber, 'Open Price');
        $closePrice = $this->parseDecimal($mapped['Close Price'] ?? '', $lineNumber, 'Close Price');
        $rawData = array_map($this->sanitize(...), $mapped);

        if ($quantity->isZero()) {
            throw new \InvalidArgumentException(sprintf('Volume must be greater than zero on line %d.', $lineNumber));
        }

        return [
            new NormalizedTransaction(
                id: TransactionId::generate(),
                isin: null,
                symbol: $symbol,
                type: TransactionType::BUY,
                date: $openDate,
                quantity: $quantity,
                pricePerUnit: Money::of($openPrice, $currency),
                commission: Money::zero($currency),
                broker: $this->brokerId(),
                description: $this->sanitize(sprintf('XTB open: BUY %s %s @ %s', $quantity, $symbol, $openPrice)),
                rawData: $rawData,
            ),
            new NormalizedTransaction(
                id: TransactionId::generate(),
                isin: null,
                symbol: $symbol,
                type: TransactionType::SELL,
                date: $closeDate,
                quantity: $quantity,
                pricePerUnit: Money::of($closePrice, $currency),
                commission: Money::zero($currency),
                broker: $this->brokerId(),
                description: $this->sanitize(sprintf('XTB close: SELL %s %s @ %s', $quantity, $symbol, $closePrice)),
                rawData: $rawData,
            ),
        ];
    }

    /**
     * @param array<string, string> $mapped
     */
    private function buildTradeFromCashOperation(
        array $mapped,
        int $lineNumber,
        TransactionType $transactionType,
        ?CurrencyCode $accountCurrency,
    ): NormalizedTransaction {
        $currency = $accountCurrency ?? throw new \InvalidArgumentException(sprintf(
            'Unable to determine XTB account currency for trade on line %d.',
            $lineNumber,
        ));
        $comment = trim($mapped['Comment'] ?? '');

        if (preg_match(self::TRADE_COMMENT_PATTERN, $comment, $matches) !== 1) {
            throw new \InvalidArgumentException(sprintf('Unsupported XTB trade comment format on line %d.', $lineNumber));
        }

        $quantity = BigDecimal::of(explode('/', $matches[2], 2)[0])->abs();

        if ($quantity->isZero()) {
            throw new \InvalidArgumentException(sprintf('Trade quantity must be greater than zero on line %d.', $lineNumber));
        }

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: $this->resolveSymbol($mapped),
            type: $transactionType,
            date: $this->parseExcelDateTime($mapped['Time'] ?? '', $lineNumber, 'Time'),
            quantity: $quantity,
            pricePerUnit: Money::of(BigDecimal::of($matches[3])->__toString(), $currency),
            commission: Money::zero($currency),
            broker: $this->brokerId(),
            description: $this->sanitize($comment),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * @param array<string, string> $mapped
     */
    private function buildCashOperationTransaction(
        array $mapped,
        int $lineNumber,
        TransactionType $transactionType,
        ?CurrencyCode $accountCurrency,
    ): NormalizedTransaction {
        $currency = $accountCurrency ?? throw new \InvalidArgumentException(sprintf(
            'Unable to determine XTB account currency for cash operation on line %d.',
            $lineNumber,
        ));
        $symbol = $this->resolveSymbol($mapped);
        $amount = $this->parseDecimal($mapped['Amount'] ?? '', $lineNumber, 'Amount');
        $date = $this->parseExcelDateTime($mapped['Time'] ?? '', $lineNumber, 'Time');

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: $symbol,
            type: $transactionType,
            date: $date,
            quantity: BigDecimal::one(),
            pricePerUnit: Money::of(BigDecimal::of($amount)->abs()->__toString(), $currency),
            commission: Money::zero($currency),
            broker: $this->brokerId(),
            description: $this->sanitize(trim($mapped['Comment'] ?? $mapped['Instrument'] ?? $symbol)),
            rawData: array_map($this->sanitize(...), $mapped),
        );
    }

    /**
     * @param list<list<string>> $rows
     * @param list<string> $requiredHeaders
     */
    private function findHeaderRowIndex(array $rows, array $requiredHeaders): ?int
    {
        foreach ($rows as $index => $row) {
            $headers = $this->normalizeHeaders($row);

            if (array_diff($requiredHeaders, $headers) === []) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $row
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        return array_map(static fn (string $value): string => trim($value), $row);
    }

    /**
     * @param list<string> $headers
     * @param list<string> $row
     * @return array<string, string>
     */
    private function mapRowToHeaders(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $mapped[$header] = $row[$index] ?? '';
        }

        return $mapped;
    }

    /**
     * @param list<string> $row
     */
    private function isDataRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveAccountCurrency(string $filename): ?CurrencyCode
    {
        if ($filename !== '' && preg_match('/(?:^|_)([A-Z]{3})(?=_)/', strtoupper($filename), $matches) === 1) {
            return CurrencyCode::tryFrom($matches[1]);
        }

        return null;
    }

    /**
     * @param array<string, string> $mapped
     */
    private function resolveSymbol(array $mapped): string
    {
        $symbol = $this->sanitize(trim($mapped['Ticker'] ?? ''));

        if ($symbol !== '') {
            return $symbol;
        }

        $symbol = $this->sanitize(trim($mapped['Instrument'] ?? ''));

        if ($symbol === '') {
            throw new \InvalidArgumentException('Missing ticker/instrument symbol.');
        }

        return $symbol;
    }

    private function parseExcelDateTime(string $value, int $lineNumber, string $field): \DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid %s value on line %d.', $field, $lineNumber));
        }

        $seconds = (int) round(((float) $value) * 86400);
        $baseTimestamp = strtotime('1899-12-30 00:00:00 UTC');

        if ($baseTimestamp === false) {
            throw new \RuntimeException('Failed to initialize Excel date base timestamp.');
        }

        return (new \DateTimeImmutable('@' . ($baseTimestamp + $seconds)))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    private function parseDecimal(string $value, int $lineNumber, string $field): string
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid %s value on line %d.', $field, $lineNumber));
        }

        return BigDecimal::of($value)->__toString();
    }
}
