<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use PHPUnit\Framework\TestCase;

final class DegiroAccountStatementAdapterTest extends TestCase
{
    private DegiroAccountStatementAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DegiroAccountStatementAdapter();
    }

    public function testBrokerIdReturnsDegiro(): void
    {
        self::assertSame('degiro', $this->adapter->brokerId()->toString());
    }

    public function testSupportsAccountStatementFormat(): void
    {
        $content = "Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,";

        self::assertTrue($this->adapter->supports($content, 'account_statement.csv'));
    }

    public function testDoesNotSupportTransactionsFormat(): void
    {
        $content = "Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price\n15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25";

        self::assertFalse($this->adapter->supports($content, 'transactions.csv'));
    }

    public function testDoesNotSupportEmptyContent(): void
    {
        self::assertFalse($this->adapter->supports('', 'account.csv'));
    }

    public function testDoesNotSupportIbkrFormat(): void
    {
        $content = "Statement,Header,Field Name,Field Value\nStatement,Data,BrokerName,Interactive Brokers";

        self::assertFalse($this->adapter->supports($content, 'activity.csv'));
    }

    /**
     * P2-020: Account Statement adapter must NOT match a real IBKR CSV file.
     */
    public function testDoesNotSupportRealIbkrCsvFile(): void
    {
        $ibkrFixture = file_get_contents(__DIR__ . '/../../Fixtures/ibkr_activity_sample.csv');
        self::assertIsString($ibkrFixture);

        self::assertFalse(
            $this->adapter->supports($ibkrFixture, 'ibkr_activity.csv'),
            'DegiroAccountStatementAdapter must not match a real IBKR CSV file',
        );
    }

    public function testParsesDividend(): void
    {
        $csv = $this->buildCsv('10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::DIVIDEND, $tx->type);
        self::assertSame('APPLE INC', $tx->symbol);
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('2.50'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
        self::assertNotNull($tx->isin);
        self::assertSame('US0378331005', $tx->isin->toString());
        self::assertTrue($tx->commission->isZero());
    }

    public function testParsesWithholdingTax(): void
    {
        $csv = $this->buildCsv('10-05-2025,08:00,APPLE INC,US0378331005,Dividendbelasting,,-0.38,USD,1502.12,USD,');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::WITHHOLDING_TAX, $tx->type);
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('0.38'));
        self::assertNotNull($tx->isin);
    }

    public function testSkipsNonDividendRows(): void
    {
        $csv = $this->buildCsv(
            "10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,\n"
            . "10-05-2025,09:00,,, iDEAL Deposit,,500.00,EUR,2002.50,EUR,\n"
            . '10-05-2025,10:00,,,Flatex Interest,,-0.05,EUR,2002.45,EUR,',
        );

        $result = $this->adapter->parse($csv);

        // Only the dividend row should be parsed
        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::DIVIDEND, $result->transactions[0]->type);
    }

    public function testFullSampleFile(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/degiro_account_statement_sample.csv';
        $csvContent = file_get_contents($fixturePath);
        self::assertIsString($csvContent);

        self::assertTrue($this->adapter->supports($csvContent, 'degiro_account_statement.csv'));

        $result = $this->adapter->parse($csvContent);

        self::assertCount(0, $result->errors, $this->formatErrors($result->errors));

        // 2 dividends + 1 WHT = 3 transactions
        self::assertCount(3, $result->transactions);

        $types = array_map(
            static fn ($tx) => $tx->type,
            $result->transactions,
        );

        self::assertCount(2, array_filter($types, static fn ($t) => $t === TransactionType::DIVIDEND));
        self::assertCount(1, array_filter($types, static fn ($t) => $t === TransactionType::WITHHOLDING_TAX));

        self::assertSame('degiro', $result->metadata->broker->toString());
        self::assertSame(3, $result->metadata->totalTransactions);
    }

    public function testReturnsCorrectMetadata(): void
    {
        $csv = $this->buildCsv(
            "10-05-2025,08:00,APPLE INC,US0378331005,Dividend,,2.50,USD,1502.50,USD,\n"
            . '15-08-2025,08:00,VWCE,IE00BK5BQT80,Dividend,,1.20,EUR,2340.20,EUR,',
        );

        $result = $this->adapter->parse($csv);

        self::assertSame('degiro', $result->metadata->broker->toString());
        self::assertSame(2, $result->metadata->totalTransactions);
        self::assertSame(0, $result->metadata->totalErrors);
        self::assertNotNull($result->metadata->dateFrom);
        self::assertNotNull($result->metadata->dateTo);
        self::assertSame('2025-05-10', $result->metadata->dateFrom->format('Y-m-d'));
        self::assertSame('2025-08-15', $result->metadata->dateTo->format('Y-m-d'));
    }

    public function testParseEmptyContentReturnsNoTransactions(): void
    {
        $result = $this->adapter->parse('');

        self::assertCount(0, $result->transactions);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    private function buildCsv(string $dataRows): string
    {
        $header = 'Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID';

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
