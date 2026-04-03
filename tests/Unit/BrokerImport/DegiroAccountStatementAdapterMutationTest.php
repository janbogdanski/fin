<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for DegiroAccountStatementAdapter.
 * Targets: priority(), supports() logical conditions, line numbers,
 * detectCurrencyAfterColumn, resolveCurrency, buildCanonicalMap.
 */
final class DegiroAccountStatementAdapterMutationTest extends TestCase
{
    private DegiroAccountStatementAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DegiroAccountStatementAdapter();
    }

    public function testPriorityReturnsExactly50(): void
    {
        self::assertSame(50, $this->adapter->priority());
    }

    /**
     * Kills line number mutants: $lineNumber = $i + 1 (changed to $i+0, $i+2, $i-1).
     */
    public function testErrorLineNumberIsCorrect(): void
    {
        $csv = $this->buildCsv(
            "10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,\n"
            . 'invalid-date,08:00,MSFT,US5949181045,Dividend,,1.20,USD,1503.70,USD,',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        // Line 3: header=1, first data=2, second data=3
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills missing column error lineNumber: ParseError(lineNumber: 1, ...).
     */
    public function testMissingColumnsErrorHasLineNumber1(): void
    {
        $csv = "Date,Time,Product\n10-05-2025,08:00,APPLE INC";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertSame(1, $result->errors[0]->lineNumber);
    }

    /**
     * Kills supports() LogicalAnd mutations: each condition must be individually required.
     * Header without 'Description' should not match.
     */
    public function testDoesNotSupportHeaderWithoutDescription(): void
    {
        $content = "Date,Time,Product,ISIN,Change,Balance\n10-05-2025,08:00,APPLE INC,US0378331005,2.50,1502.50";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Header without 'Change' should not match.
     */
    public function testDoesNotSupportHeaderWithoutChange(): void
    {
        $content = "Date,Time,Product,ISIN,Description,Balance\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,1502.50";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Header without 'Balance' should not match.
     */
    public function testDoesNotSupportHeaderWithoutBalance(): void
    {
        $content = "Date,Time,Product,ISIN,Description,Change\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,2.50";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills LogicalAnd on $lacksTradeColumns: header with 'Quantity' should not match.
     */
    public function testDoesNotSupportHeaderWithQuantity(): void
    {
        $content = "Date,Time,Product,ISIN,Description,Change,Balance,Quantity\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,2.50,1502.50,10";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills LogicalAnd on $lacksTradeColumns: header with 'Aantal' should not match.
     */
    public function testDoesNotSupportHeaderWithAantal(): void
    {
        $content = "Date,Time,Product,ISIN,Description,Change,Balance,Aantal\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,2.50,1502.50,10";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills LogicalAnd on $isNotIbkr: header with 'Statement,' should not match.
     */
    public function testDoesNotSupportHeaderWithStatementPrefix(): void
    {
        $content = "Statement,Description,Change,Balance\nStatement,Data,2.50,1502.50";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills LogicalAnd on $isNotIbkr: header with 'Header,' should not match.
     */
    public function testDoesNotSupportHeaderWithHeaderPrefix(): void
    {
        $content = "Header,Description,Change,Balance\nHeader,Data,2.50,1502.50";

        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills supports() return: $hasAccountColumns && $lacksTradeColumns && $isNotIbkr.
     * Without $hasAccountColumns, random CSV should not match.
     */
    public function testDoesNotSupportRandomCSV(): void
    {
        $content = "Name,Age,City\nJan,30,Warsaw";

        self::assertFalse($this->adapter->supports($content, 'random.csv'));
    }

    /**
     * Kills FalseValue mutant: strtok returns false guard.
     */
    public function testSupportsReturnsFalseForNewlineOnly(): void
    {
        self::assertFalse($this->adapter->supports("\n", 'account.csv'));
    }

    /**
     * Kills resolveCurrency: empty currency defaults to EUR.
     */
    public function testEmptyCurrencyDefaultsToEUR(): void
    {
        // Build CSV without currency in Change column's neighbor
        $csv = "Date,Time,Product,ISIN,Description,FX,Change,Balance,Order ID\n"
            . '10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,1502.50,';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('EUR', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    /**
     * Kills WHT keyword detection: 'dividend tax' and 'withholding tax' keywords.
     */
    public function testDetectsDividendTaxKeyword(): void
    {
        $csv = $this->buildCsv('10-05-2025,08:00,APPLE INC,US0378331005,Dividend Tax,,-0.38,USD,1502.12,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::WITHHOLDING_TAX, $result->transactions[0]->type);
    }

    public function testDetectsWithholdingTaxKeyword(): void
    {
        $csv = $this->buildCsv('10-05-2025,08:00,APPLE INC,US0378331005,Withholding Tax,,-0.38,USD,1502.12,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::WITHHOLDING_TAX, $result->transactions[0]->type);
    }

    /**
     * Kills error rawData sanitization mutant.
     */
    public function testErrorRawDataIsSanitized(): void
    {
        $csv = $this->buildCsv('invalid-date,08:00,=CMD("calc"),US0378331005,Dividend,,2.50,USD,1502.50,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertNotNull($result->errors[0]->rawData);

        foreach ($result->errors[0]->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }

    /**
     * Kills date-only fallback parsing (no time).
     */
    public function testParsesDateWithoutTime(): void
    {
        $csv = $this->buildCsv('10-05-2025,,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2025-05-10', $result->transactions[0]->date->format('Y-m-d'));
    }

    private function buildCsv(string $dataRows): string
    {
        $header = 'Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID';

        return $header . "\n" . $dataRows;
    }
}
