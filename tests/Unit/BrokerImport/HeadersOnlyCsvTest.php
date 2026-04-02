<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\TestCase;

/**
 * P3-006: All adapters must handle CSV files with headers but no data rows.
 *
 * Expected behavior: parse returns zero transactions, zero errors.
 * The adapter should NOT throw an exception.
 */
final class HeadersOnlyCsvTest extends TestCase
{
    public function testDegiroTransactionsHeadersOnly(): void
    {
        $adapter = new DegiroTransactionsAdapter();
        $csv = 'Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price,,Local value,,Value,,Exchange rate,Transaction costs,,Total,,Order ID';

        self::assertTrue($adapter->supports($csv, 'transactions.csv'));

        $result = $adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testDegiroAccountStatementHeadersOnly(): void
    {
        $adapter = new DegiroAccountStatementAdapter();
        $csv = 'Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID';

        self::assertTrue($adapter->supports($csv, 'account_statement.csv'));

        $result = $adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testRevolutStocksHeadersOnly(): void
    {
        $adapter = new RevolutStocksAdapter();
        $csv = 'Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate';

        self::assertTrue($adapter->supports($csv, 'revolut.csv'));

        $result = $adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testRevolutStocksNewerFormatHeadersOnly(): void
    {
        $adapter = new RevolutStocksAdapter();
        $csv = 'Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission';

        self::assertTrue($adapter->supports($csv, 'revolut.csv'));

        $result = $adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    public function testBossaHistoryHeadersOnly(): void
    {
        $adapter = new BossaHistoryAdapter();
        $csv = 'Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta;ISIN';

        self::assertTrue($adapter->supports($csv, 'bossa.csv'));

        $result = $adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(0, $result->errors);
        self::assertSame(0, $result->metadata->totalTransactions);
    }

    /**
     * IBKR has section-based format. Headers-only means just the Statement header
     * with no Data rows.
     */
    public function testIBKRActivityHeadersOnly(): void
    {
        $adapter = new IBKRActivityAdapter();
        $csv = "Statement,Header,Field Name,Field Value\nStatement,Data,Title,Activity Statement\nStatement,Data,BrokerName,Interactive Brokers";

        self::assertTrue($adapter->supports($csv, 'activity.csv'));

        $result = $adapter->parse($csv);

        // IBKR with only Statement header section — no Trades/Dividends sections
        self::assertCount(0, $result->transactions);
        self::assertSame(0, $result->metadata->totalTransactions);
    }
}
