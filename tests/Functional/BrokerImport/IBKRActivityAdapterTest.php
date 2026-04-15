<?php

declare(strict_types=1);

namespace App\Tests\Functional\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use PHPUnit\Framework\TestCase;

final class IBKRActivityAdapterTest extends TestCase
{
    private IBKRActivityAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new IBKRActivityAdapter();
    }

    public function testBrokerIdReturnsIbkr(): void
    {
        self::assertSame('ibkr', $this->adapter->brokerId()->toString());
    }

    public function testSupportsIbkrCsv(): void
    {
        $content = "Statement,Header,Field Name,Field Value\nStatement,Data,BrokerName,Interactive Brokers";

        self::assertTrue($this->adapter->supports($content, 'activity.csv'));
    }

    public function testSupportsStatementHeader(): void
    {
        $content = "Statement,Header,Field Name,Field Value\nStatement,Data,Title,Activity Statement";

        self::assertTrue($this->adapter->supports($content, 'export.csv'));
    }

    public function testDoesNotSupportEmptyContent(): void
    {
        self::assertFalse($this->adapter->supports('', 'activity.csv'));
    }

    public function testDoesNotSupportOtherCsv(): void
    {
        $degiroContent = "Date,Time,Product,ISIN,Exchange,Venue,Quantity,Price\n15-03-2024,14:30,APPLE INC,US0378331005,,XNAS,10,171.25";

        self::assertFalse($this->adapter->supports($degiroContent, 'degiro_transactions.csv'));
    }

    public function testParsesBuyTrade(): void
    {
        $csv = $this->buildTradesCsv(
            '"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('10'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('171.25'));
        self::assertTrue($tx->commission->amount()->isEqualTo('1.00'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
        self::assertNotNull($tx->isin);
        self::assertSame('US0378331005', $tx->isin->toString());
    }

    public function testParsesSellTrade(): void
    {
        $csv = $this->buildTradesCsv(
            '"2024-09-10, 11:00:00",-8,415.20,-1.25,,US5949181045',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::SELL, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('8'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('415.20'));
        self::assertTrue($tx->commission->amount()->isEqualTo('1.25'));
    }

    public function testParsesDividend(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,2024-05-10,AAPL(US0378331005) Cash Dividend USD 0.25 per Share (Ordinary Dividend),2.50
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::DIVIDEND, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('2.50'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
        self::assertNotNull($tx->isin);
        self::assertSame('US0378331005', $tx->isin->toString());
        self::assertTrue($tx->commission->isZero());
    }

    public function testParsesWithholdingTax(): void
    {
        $csv = <<<'CSV'
            Withholding Tax,Header,Currency,Date,Description,Amount
            Withholding Tax,Data,USD,2024-05-10,AAPL(US0378331005) Cash Dividend USD 0.25 per Share - US Tax,-0.38
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::WITHHOLDING_TAX, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('0.38'));
        self::assertNotNull($tx->isin);
    }

    public function testHandlesMissingColumnsGracefully(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol
            Trades,Data,Order,Stocks,USD,AAPL
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame('Trades', $result->errors[0]->section);
        self::assertStringContainsString('Missing required columns', $result->errors[0]->message);
    }

    public function testHandlesInvalidDateGracefully(): void
    {
        $csv = $this->buildTradesCsv(
            'not-a-date,10,171.25,-1.00,,US0378331005',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Cannot parse date', $result->errors[0]->message);
    }

    public function testSanitizesCsvInjection(): void
    {
        $csv = <<<'CSV'
            Dividends,Header,Currency,Date,Description,Amount
            Dividends,Data,USD,2024-05-10,=CMD('calc') AAPL(US0378331005) Dividend,2.50
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertStringNotContainsString('=CMD', $tx->description);
        self::assertStringStartsNotWith('=', $tx->description);
        self::assertStringStartsNotWith('+', $tx->description);
        self::assertStringStartsNotWith('-', $tx->description);
        self::assertStringStartsNotWith('@', $tx->description);

        // Also check rawData values are sanitized
        foreach ($tx->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }

    public function testReturnsCorrectMetadata(): void
    {
        $csv = $this->buildTradesCsv(
            '"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005' . "\n"
            . 'Trades,Data,Order,Stocks,USD,MSFT,"2024-09-10, 11:00:00",-8,415.20,-1.25,,US5949181045',
        );

        $result = $this->adapter->parse($csv);

        self::assertSame('ibkr', $result->metadata->broker->toString());
        self::assertSame(2, $result->metadata->totalTransactions);
        self::assertSame(0, $result->metadata->totalErrors);
        self::assertContains('Trades', $result->metadata->sectionsFound);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
        self::assertSame('2024-03-15', $result->metadata->dateFrom->format('Y-m-d'));
        self::assertSame('2024-09-10', $result->metadata->dateTo->format('Y-m-d'));
    }

    public function testSkipsSubTotalAndTotalRows(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005
            Trades,Data,SubTotal,Stocks,USD,,,,,,
            Trades,Data,Total,,,,,,,,
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
    }

    public function testSkipsNonStockAssetCategories(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,"2024-03-15, 14:30:00",10,171.25,-1.00,,US0378331005
            Trades,Data,Order,Forex,USD,EUR.USD,"2024-03-15, 14:30:00",1000,1.0850,-2.00,,
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('Forex', $result->warnings[0]->message);
    }

    public function testFullSampleFile(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/ibkr_activity_sample.csv';
        $csvContent = file_get_contents($fixturePath);
        self::assertIsString($csvContent);

        self::assertTrue($this->adapter->supports($csvContent, 'ibkr_activity_sample.csv'));

        $result = $this->adapter->parse($csvContent);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        // 3 trades + 1 dividend + 1 WHT = 5 transactions
        self::assertCount(5, $result->transactions);

        $types = array_map(
            static fn ($tx) => $tx->type,
            $result->transactions,
        );

        self::assertCount(2, array_filter($types, static fn ($t) => $t === TransactionType::BUY));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::SELL));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::DIVIDEND));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::WITHHOLDING_TAX));

        self::assertSame('ibkr', $result->metadata->broker->toString());
        self::assertSame(5, $result->metadata->totalTransactions);
        self::assertContains('Trades', $result->metadata->sectionsFound);
        self::assertContains('Dividends', $result->metadata->sectionsFound);
        self::assertContains('Withholding Tax', $result->metadata->sectionsFound);
    }

    public function testParsesTradeWithMillisecondTimestamp(): void
    {
        $csv = $this->buildTradesCsv(
            '"2024-03-15, 14:30:00.123",10,171.25,-1.00,,US0378331005',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('2024-03-15', $tx->date->format('Y-m-d'));
        self::assertSame('14:30:00', $tx->date->format('H:i:s'));
    }

    public function testParsesTradeWithCompactMillisecondTimestamp(): void
    {
        $csv = $this->buildTradesCsv(
            '"20240315;143000456",10,171.25,-1.00,,US0378331005',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        $tx = $result->transactions[0];
        self::assertSame('2024-03-15', $tx->date->format('Y-m-d'));
        self::assertSame('14:30:00', $tx->date->format('H:i:s'));
    }

    public function testParseEmptyContentReturnsEmptyResult(): void
    {
        $result = $this->adapter->parse('');

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    private function buildTradesCsv(string $dataRow): string
    {
        return <<<CSV
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee,Code,ISIN
            Trades,Data,Order,Stocks,USD,AAPL,{$dataRow}
            CSV;
    }

    /**
     * @param list<\App\BrokerImport\Application\DTO\ParseError> $errors
     */
    private function formatErrors(array $errors): string
    {
        return implode("\n", array_map(
            static fn ($e) => sprintf('[Line %d, %s] %s', $e->lineNumber, $e->section, $e->message),
            $errors,
        ));
    }
}
