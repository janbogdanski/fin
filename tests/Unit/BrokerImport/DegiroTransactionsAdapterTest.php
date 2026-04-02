<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use PHPUnit\Framework\TestCase;

final class DegiroTransactionsAdapterTest extends TestCase
{
    private DegiroTransactionsAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DegiroTransactionsAdapter();
    }

    public function testBrokerIdReturnsDegiro(): void
    {
        self::assertSame('degiro', $this->adapter->brokerId()->toString());
    }

    public function testSupportsEnglishFormat(): void
    {
        $content = "Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price\n15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25";

        self::assertTrue($this->adapter->supports($content, 'transactions.csv'));
    }

    public function testSupportsDutchFormat(): void
    {
        $content = "Datum,Tijd,Product,ISIN,Beurs,Uitvoeringsplaats,Aantal,Koers\n15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25";

        self::assertTrue($this->adapter->supports($content, 'transacties.csv'));
    }

    public function testDoesNotSupportEmptyContent(): void
    {
        self::assertFalse($this->adapter->supports('', 'transactions.csv'));
    }

    public function testDoesNotSupportIbkrFormat(): void
    {
        $ibkrContent = "Statement,Header,Field Name,Field Value\nStatement,Data,BrokerName,Interactive Brokers";

        self::assertFalse($this->adapter->supports($ibkrContent, 'activity.csv'));
    }

    /**
     * P2-020: Degiro adapter must NOT match a real IBKR CSV file.
     * Regression test for false positive where generic header checks
     * (Date, Time, ISIN, Product) could match IBKR section headers.
     */
    public function testDoesNotSupportRealIbkrCsvFile(): void
    {
        $ibkrFixture = file_get_contents(__DIR__ . '/../../Fixtures/ibkr_activity_sample.csv');
        self::assertIsString($ibkrFixture);

        self::assertFalse(
            $this->adapter->supports($ibkrFixture, 'ibkr_activity.csv'),
            'DegiroTransactionsAdapter must not match a real IBKR CSV file',
        );
    }

    public function testParsesBuyTransaction(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('APPLE INC', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('10'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('171.25'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
        self::assertTrue($tx->commission->amount()->isEqualTo('1.00'));
        self::assertSame('degiro', $tx->broker->toString());
    }

    public function testParsesSellTransaction(): void
    {
        $csv = $this->buildCsv('10-09-2025,11:00,APPLE INC,US0378331005,NASDAQ,XNAS,-8,195.50,USD,1564.00,USD,1442.14,EUR,1.0845,-1.00,EUR,1441.14,EUR,xyz789');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::SELL, $tx->type);
        self::assertTrue($tx->quantity->isEqualTo('8'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('195.50'));
    }

    public function testParsesMultipleCurrencies(): void
    {
        $csv = $this->buildCsv(
            "15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123\n"
            . '20-06-2025,09:45,VWCE,IE00BK5BQT80,XETRA,XETA,5,108.50,EUR,-542.50,EUR,-542.50,EUR,,-2.00,EUR,-544.50,EUR,def456',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(2, $result->transactions);
        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        self::assertSame('USD', $result->transactions[0]->pricePerUnit->currency()->value);
        self::assertSame('EUR', $result->transactions[1]->pricePerUnit->currency()->value);
    }

    public function testExtractsISIN(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertNotNull($tx->isin);
        self::assertSame('US0378331005', $tx->isin->toString());
    }

    public function testHandlesMissingColumnsGracefully(): void
    {
        // Incomplete headers — adapter returns errors, not exception
        $csv = "Date,Time,Product\n15-03-2025,14:30,APPLE INC";

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertGreaterThan(0, \count($result->errors));
    }

    public function testSanitizesCsvInjection(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,=CMD("calc") APPLE,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertStringNotContainsString('=CMD', $tx->symbol);
        self::assertStringStartsNotWith('=', $tx->symbol);
        self::assertStringStartsNotWith('+', $tx->description);
        self::assertStringStartsNotWith('-', $tx->description);
        self::assertStringStartsNotWith('@', $tx->description);

        foreach ($tx->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }

    public function testReturnsCorrectMetadata(): void
    {
        $csv = $this->buildCsv(
            "15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123\n"
            . '10-09-2025,11:00,APPLE INC,US0378331005,NASDAQ,XNAS,-8,195.50,USD,1564.00,USD,1442.14,EUR,1.0845,-1.00,EUR,1441.14,EUR,xyz789',
        );

        $result = $this->adapter->parse($csv);

        self::assertSame('degiro', $result->metadata->broker->toString());
        self::assertSame(2, $result->metadata->totalTransactions);
        self::assertSame(0, $result->metadata->totalErrors);
        self::assertContains('Transactions', $result->metadata->sectionsFound);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
        self::assertSame('2025-03-15', $result->metadata->dateFrom->format('Y-m-d'));
        self::assertSame('2025-09-10', $result->metadata->dateTo->format('Y-m-d'));
    }

    public function testFullSampleFile(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/degiro_transactions_sample.csv';
        $csvContent = file_get_contents($fixturePath);
        self::assertIsString($csvContent);

        self::assertTrue($this->adapter->supports($csvContent, 'degiro_transactions.csv'));

        $result = $this->adapter->parse($csvContent);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        // 4 transactions: 2 BUY, 2 SELL
        self::assertCount(4, $result->transactions);

        $types = array_map(
            static fn ($tx) => $tx->type,
            $result->transactions,
        );

        self::assertCount(2, array_filter($types, static fn ($t) => $t === TransactionType::BUY));
        self::assertCount(2, array_filter($types, static fn ($t) => $t === TransactionType::SELL));

        self::assertSame('degiro', $result->metadata->broker->toString());
        self::assertSame(4, $result->metadata->totalTransactions);
        self::assertContains('Transactions', $result->metadata->sectionsFound);

        // Verify ISINs
        $isins = array_map(
            static fn ($tx) => $tx->isin?->toString(),
            $result->transactions,
        );
        self::assertContains('US0378331005', $isins);
        self::assertContains('IE00BK5BQT80', $isins);

        // Verify currencies
        $currencies = array_unique(array_map(
            static fn ($tx) => $tx->pricePerUnit->currency()->value,
            $result->transactions,
        ));
        self::assertContains('USD', $currencies);
        self::assertContains('EUR', $currencies);
    }

    public function testParseEmptyContentReturnsNoTransactions(): void
    {
        $result = $this->adapter->parse('');

        self::assertCount(0, $result->transactions);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testHandlesInvalidDateGracefully(): void
    {
        $csv = $this->buildCsv('not-a-date,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Cannot parse', $result->errors[0]->message);
    }

    public function testHandlesZeroQuantityRow(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,0,171.25,USD,0.00,USD,0.00,EUR,1.0836,0.00,EUR,0.00,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::BUY, $result->transactions[0]->type);
        self::assertTrue($result->transactions[0]->quantity->isEqualTo('0'));
    }

    public function testHandlesVeryLargeAmounts(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,BERKSHIRE A,US0846707026,NYSE,XNYS,1,999999.99,USD,-999999.99,USD,-922345.67,EUR,1.0836,-5.00,EUR,-922350.67,EUR,big123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertTrue($result->transactions[0]->pricePerUnit->amount()->isEqualTo('999999.99'));
    }

    public function testParsesMultipleTransactionTypesInOneFile(): void
    {
        $csv = $this->buildCsv(
            "15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123\n"
            . '10-09-2025,11:00,APPLE INC,US0378331005,NASDAQ,XNAS,-8,195.50,USD,1564.00,USD,1442.14,EUR,1.0845,-1.00,EUR,1441.14,EUR,xyz789',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(2, $result->transactions);

        $types = array_map(static fn ($tx) => $tx->type, $result->transactions);
        self::assertContains(TransactionType::BUY, $types);
        self::assertContains(TransactionType::SELL, $types);
    }

    private function buildCsv(string $dataRows): string
    {
        $header = 'Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price,,Local value,,Value,,Exchange rate,Transaction costs,,Total,,Order ID';

        return $header . "\n" . $dataRows;
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
