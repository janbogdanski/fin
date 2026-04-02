<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\TestCase;

final class RevolutStocksAdapterTest extends TestCase
{
    private RevolutStocksAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new RevolutStocksAdapter();
    }

    public function testBrokerIdReturnsRevolut(): void
    {
        self::assertSame('revolut', $this->adapter->brokerId()->toString());
    }

    public function testSupportsRevolutFormat(): void
    {
        $content = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00";

        self::assertTrue($this->adapter->supports($content, 'revolut_trades.csv'));
    }

    public function testSupportsNewerRevolutFormat(): void
    {
        $content = "Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,COMPLETED,0.00";

        self::assertTrue($this->adapter->supports($content, 'revolut.csv'));
    }

    public function testDoesNotSupportOtherFormat(): void
    {
        $ibkrContent = "Statement,Header,Field Name,Field Value\nStatement,Data,BrokerName,Interactive Brokers";

        self::assertFalse($this->adapter->supports($ibkrContent, 'ibkr.csv'));
    }

    public function testDoesNotSupportEmptyContent(): void
    {
        self::assertFalse($this->adapter->supports('', 'revolut.csv'));
    }

    public function testParsesBuyTransaction(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('10'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('171.25'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
        self::assertSame('revolut', $tx->broker->toString());
    }

    public function testParsesSellTransaction(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-06-20,MSFT,SELL,5,415.20,2076.00,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::SELL, $tx->type);
        self::assertSame('MSFT', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('5'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('415.20'));
    }

    public function testParsesDividend(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-05-10,AAPL,DIVIDEND,0,0.25,2.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::DIVIDEND, $tx->type);
        self::assertSame('AAPL', $tx->symbol);
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('0.25'));
    }

    public function testParsesCustodyFee(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-12-01,AAPL,CUSTODY FEE,0,0.00,1.20,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::FEE, $tx->type);
    }

    public function testHandlesMissingISIN(): void
    {
        // Use unknown ticker that is NOT in TickerToISINMap
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,XYZUNK,BUY,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertNull($tx->isin);

        // Should produce a warning about missing ISIN
        $isinWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'ISIN'),
        );
        self::assertNotEmpty($isinWarnings);
    }

    public function testHandlesCommissionColumn(): void
    {
        $csv = "Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,COMPLETED,1.50";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('1.50'));
    }

    public function testMissingCommissionDefaultsToZero(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->isZero());
    }

    public function testSanitizesCsvInjection(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,=CMD('calc'),BUY,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertStringNotContainsString('=CMD', $tx->symbol);
        self::assertStringStartsNotWith('=', $tx->symbol);

        foreach ($tx->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
            self::assertStringStartsNotWith('+', $value);
            self::assertStringStartsNotWith('-', $value);
            self::assertStringStartsNotWith('@', $value);
        }
    }

    public function testHandlesDateWithSlashFormat(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n15/03/2024,AAPL,BUY,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    public function testSkipsUnknownTransactionType(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n2024-03-15,AAPL,UNKNOWN_TYPE,10,171.25,1712.50,USD,1.00";

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertNotEmpty($result->warnings);
    }

    public function testReturnsCorrectMetadata(): void
    {
        $csv = <<<'CSV'
            Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate
            2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00
            2024-06-20,MSFT,SELL,5,415.20,2076.00,USD,1.00
            CSV;

        $result = $this->adapter->parse($csv);

        self::assertSame('revolut', $result->metadata->broker->toString());
        self::assertSame(2, $result->metadata->totalTransactions);
        self::assertSame(0, $result->metadata->totalErrors);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
    }

    public function testFullSampleFile(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/revolut_stocks_sample.csv';
        $csvContent = file_get_contents($fixturePath);
        self::assertIsString($csvContent);

        self::assertTrue($this->adapter->supports($csvContent, 'revolut_stocks_sample.csv'));

        $result = $this->adapter->parse($csvContent);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));
        self::assertCount(4, $result->transactions);

        $types = array_map(
            static fn ($tx) => $tx->type,
            $result->transactions,
        );

        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::BUY));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::SELL));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::DIVIDEND));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::FEE));

        self::assertSame('revolut', $result->metadata->broker->toString());
        self::assertSame(4, $result->metadata->totalTransactions);
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
