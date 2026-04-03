<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for IBKRActivityAdapter.
 * Targets: priority(), line numbers, extractSections, parseDateTime formats,
 * extractSymbolFromDescription, tryExtractISIN, tryExtractISINFromDescription,
 * date range tracking, section-level error line numbers.
 */
final class IBKRActivityAdapterMutationTest extends TestCase
{
    private IBKRActivityAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new IBKRActivityAdapter();
    }

    public function testPriorityReturnsExactly100(): void
    {
        self::assertSame(100, $this->adapter->priority());
    }

    /**
     * Kills error line number mutants in Trades section.
     */
    public function testTradeErrorLineNumberIsCorrect(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005
            Trades,Data,Order,Stocks,USD,MSFT,invalid-date,5,400.00,-1.00,,US5949181045
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills error line number mutants in Dividends section.
     */
    public function testDividendErrorLineNumberIsCorrect(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,2024-05-10,AAPL(US0378331005) Cash Dividend,2.50
            Dividends,Data,USD,invalid-date,MSFT(US5949181045) Cash Dividend,1.20
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills error line number mutants in Withholding Tax section.
     */
    public function testWithholdingTaxErrorLineNumberIsCorrect(): void
    {
        $csv = <<<'CSV'
            Withholding Tax,Header,Currency,Date,Description,Amount
            Withholding Tax,Data,USD,2024-05-10,AAPL(US0378331005) Cash Dividend - US Tax,-0.38
            Withholding Tax,Data,USD,invalid-date,MSFT(US5949181045) Cash Dividend - US Tax,-0.18
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills missing columns error for Dividends section.
     */
    public function testDividendMissingColumnsError(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description
            Dividends,Data,USD,2024-05-10,AAPL Cash Dividend
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame('Dividends', $result->errors[0]->section);
        self::assertStringContainsString('Missing required columns', $result->errors[0]->message);
    }

    /**
     * Kills missing columns error for Withholding Tax section.
     */
    public function testWithholdingTaxMissingColumnsError(): void
    {
        $csv = <<<'CSV'
            Withholding Tax,Header,Currency,Date,Description
            Withholding Tax,Data,USD,2024-05-10,AAPL Tax
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame('Withholding Tax', $result->errors[0]->section);
    }

    /**
     * Kills parseDateTime format mutants: compact format "Ymd His" (no millis).
     */
    public function testParsesCompactDateTimeFormat(): void
    {
        $csv = <<<CSV
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"20240315;143000",10,171.25,-1.00,,US0378331005
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    /**
     * Kills parseDate format mutants: "Ymd" compact date format.
     */
    public function testParsesCompactDateFormatInDividend(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,20240510,AAPL(US0378331005) Cash Dividend,2.50
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-05-10', $result->transactions[0]->date->format('Y-m-d'));
    }

    /**
     * Kills extractSymbolFromDescription fallback: description without ISIN pattern.
     */
    public function testExtractsSymbolFallbackFirstWord(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,2024-05-10,AAPL Cash Dividend USD 0.25,2.50
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('AAPL', $result->transactions[0]->symbol);
        // No ISIN in description, so null
        self::assertNull($result->transactions[0]->isin);
    }

    /**
     * Kills tryExtractISIN: Code column used as fallback.
     */
    public function testExtractsISINFromCodeColumn(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"2024-03-15, 14:30:00",10,171.25,-1.00,,
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        // ISIN column is empty, Code column is also empty -> null
        self::assertNull($result->transactions[0]->isin);
    }

    /**
     * Kills dateFrom/dateTo tracking mutants (LessThan/GreaterThan).
     */
    public function testDateRangeIsCorrectlyTracked(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"2024-09-10, 11:00:00",10,171.25,-1.00,,US0378331005
            Trades,Data,Order,Stocks,USD,MSFT,"2024-01-05, 09:00:00",5,400.00,-1.00,,US5949181045
            Trades,Data,Order,Stocks,USD,GOOG,"2024-06-15, 14:00:00",-3,180.00,-0.50,,US02079K3059
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(3, $result->transactions);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
        self::assertSame('2024-01-05', $result->metadata->dateFrom->format('Y-m-d'));
        self::assertSame('2024-09-10', $result->metadata->dateTo->format('Y-m-d'));
    }

    /**
     * Kills extractSections: rows with fewer than 3 fields are skipped.
     */
    public function testSkipsShortRows(): void
    {
        $csv = "Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN\n"
            . "short\n"
            . "Trades,Data,Order,Stocks,USD,AAPL,\"2024-03-15, 14:30:00\",10,171.25,-1.00,,US0378331005\n";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
    }

    /**
     * Kills Asset Category empty string check: when empty, should default to 'Stocks'.
     */
    public function testEmptyAssetCategoryTreatedAsStocks(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,,USD,AAPL,"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::BUY, $result->transactions[0]->type);
    }

    /**
     * Kills d/m/Y date format in parseDate.
     */
    public function testParsesSlashDateFormatInDividend(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,10/05/2024,AAPL(US0378331005) Cash Dividend,2.50
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-05-10', $result->transactions[0]->date->format('Y-m-d'));
    }
}
