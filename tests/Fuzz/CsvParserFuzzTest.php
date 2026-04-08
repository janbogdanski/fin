<?php

declare(strict_types=1);

namespace App\Tests\Fuzz;

use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that all CSV adapters handle malformed, adversarial, and edge-case
 * inputs gracefully. A test fails only when parse() escapes with a Throwable
 * that is NOT in the allowed set of domain/validation exceptions.
 *
 * Allowed escaping exceptions:
 *   - \InvalidArgumentException
 *   - \Brick\Math\Exception\NumberFormatException
 *   - \App\BrokerImport\Domain\Exception\* (any subclass of \DomainException)
 */
#[Group('fuzz')]
final class CsvParserFuzzTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Scenario 1: Empty string on all adapters
    // ---------------------------------------------------------------------------

    public function testEmptyStringIbkr(): void
    {
        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse(''));
    }

    public function testEmptyStringRevolut(): void
    {
        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse(''));
    }

    public function testEmptyStringDegiro(): void
    {
        $adapter = new DegiroTransactionsAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse(''));
    }

    public function testEmptyStringBossa(): void
    {
        $adapter = new BossaHistoryAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse(''));
    }

    public function testEmptyStringDegiroAccountStatement(): void
    {
        $adapter = new DegiroAccountStatementAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse(''));
    }

    // ---------------------------------------------------------------------------
    // Scenario 2: Null byte input
    // ---------------------------------------------------------------------------

    public function testNullByteIbkr(): void
    {
        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse("\x00"));
    }

    public function testNullByteRevolut(): void
    {
        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse("\x00"));
    }

    public function testNullByteDegiro(): void
    {
        $adapter = new DegiroTransactionsAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse("\x00"));
    }

    public function testNullByteBossa(): void
    {
        $adapter = new BossaHistoryAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse("\x00"));
    }

    public function testNullByteDegiroAccountStatement(): void
    {
        $adapter = new DegiroAccountStatementAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse("\x00"));
    }

    // ---------------------------------------------------------------------------
    // Scenario 3: Binary garbage
    // ---------------------------------------------------------------------------

    public function testBinaryGarbageIbkr(): void
    {
        $adapter = new IBKRActivityAdapter();
        $garbage = str_repeat("\x00\xFF\xFE\x01", 64);
        $this->assertParseReturnsResult(fn () => $adapter->parse($garbage));
    }

    public function testBinaryGarbageRevolut(): void
    {
        $adapter = new RevolutStocksAdapter();
        $garbage = str_repeat("\x00\xFF\xFE\x01", 64);
        $this->assertParseReturnsResult(fn () => $adapter->parse($garbage));
    }

    public function testBinaryGarbageDegiro(): void
    {
        $adapter = new DegiroTransactionsAdapter();
        $garbage = str_repeat("\x00\xFF\xFE\x01", 64);
        $this->assertParseReturnsResult(fn () => $adapter->parse($garbage));
    }

    public function testBinaryGarbageBossa(): void
    {
        $adapter = new BossaHistoryAdapter();
        $garbage = str_repeat("\x00\xFF\xFE\x01", 64);
        $this->assertParseReturnsResult(fn () => $adapter->parse($garbage));
    }

    public function testBinaryGarbageDegiroAccountStatement(): void
    {
        $adapter = new DegiroAccountStatementAdapter();
        $garbage = str_repeat("\x00\xFF\xFE\x01", 64);
        $this->assertParseReturnsResult(fn () => $adapter->parse($garbage));
    }

    // ---------------------------------------------------------------------------
    // Scenario 4: SQL injection in CSV symbol field
    // ---------------------------------------------------------------------------

    public function testSqlInjectionInIbkrSymbol(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee
            Trades,Data,Order,Stocks,USD,"ROBERT'); DROP TABLE;--","2024-01-15, 10:30:00",10,150.00,-1.50
            CSV;

        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testSqlInjectionInRevolutSymbol(): void
    {
        $csv = <<<'CSV'
            Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate
            2024-01-15,"ROBERT'); DROP TABLE;--",BUY,10,150.00,1500.00,USD,1.0
            CSV;

        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    // ---------------------------------------------------------------------------
    // Scenario 5: Formula injection in symbol field
    // ---------------------------------------------------------------------------

    public function testFormulaInjectionInIbkrSymbol(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee
            Trades,Data,Order,Stocks,USD,"=HYPERLINK(""evil.com"")","2024-01-15, 10:30:00",10,150.00,-1.50
            CSV;

        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testFormulaInjectionInRevolutSymbol(): void
    {
        $csv = <<<'CSV'
            Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate
            2024-01-15,"=HYPERLINK(""evil.com"")",BUY,10,150.00,1500.00,USD,1.0
            CSV;

        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    // ---------------------------------------------------------------------------
    // Scenario 6: IBKR CSV with unknown currency "SGD" — verifies P0 fix
    // The adapter's internal catch wraps resolveCurrency(); parse() must return ParseResult.
    // ---------------------------------------------------------------------------

    public function testIbkrUnknownCurrencySgdReturnsParseResult(): void
    {
        $csv = <<<'CSV'
            Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee
            Trades,Data,Order,Stocks,SGD,D05,"2024-01-15, 10:30:00",10,28.50,-0.50
            CSV;

        $adapter = new IBKRActivityAdapter();
        $result = $adapter->parse($csv);

        self::assertInstanceOf(ParseResult::class, $result);
        // Transaction must be in errors (currency rejected), not in transactions
        self::assertCount(0, $result->transactions);
        self::assertNotEmpty($result->errors);
        self::assertStringContainsString('SGD', $result->errors[0]->message);
    }

    // ---------------------------------------------------------------------------
    // Scenario 7: Bossa CSV with unknown currency "SGD" — verifies P0 fix
    // ---------------------------------------------------------------------------

    public function testBossaUnknownCurrencySgdReturnsParseResult(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . "2024-01-15;D05;K;10;28,50;285,00;0,50;SGD\n";

        $adapter = new BossaHistoryAdapter();
        $result = $adapter->parse($csv);

        self::assertInstanceOf(ParseResult::class, $result);
        self::assertCount(0, $result->transactions);
        self::assertNotEmpty($result->errors);
        self::assertStringContainsString('SGD', $result->errors[0]->message);
    }

    // ---------------------------------------------------------------------------
    // Scenario 8: Mixed line endings
    // ---------------------------------------------------------------------------

    public function testMixedLineEndingsIbkr(): void
    {
        $csv = "Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee\r\n"
            . "Trades,Data,Order,Stocks,USD,AAPL,\"2024-01-15, 10:30:00\",10,150.00,-1.50\r"
            . "Trades,Data,Order,Stocks,USD,MSFT,\"2024-01-16, 09:00:00\",5,300.00,-1.00\n";

        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testMixedLineEndingsRevolut(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\r\n"
            . "2024-01-15,AAPL,BUY,10,150.00,1500.00,USD,1.0\r"
            . "2024-01-16,MSFT,SELL,5,300.00,1500.00,USD,1.0\n";

        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    // ---------------------------------------------------------------------------
    // Scenario 9: Dash-only field in numeric position (CsvSanitizer edge case)
    // The sanitize() method preserves "-" followed by a digit, but a bare "-"
    // is not a valid number. The adapter must catch the resulting exception.
    // ---------------------------------------------------------------------------

    public function testDashOnlyFieldInIbkrQuantity(): void
    {
        $csv = "Trades,Header,DataDiscriminator,Asset Category,Currency,Symbol,Date/Time,Quantity,T. Price,Comm/Fee\n"
            . "Trades,Data,Order,Stocks,USD,AAPL,\"2024-01-15, 10:30:00\",-,150.00,-1.50\n";

        $adapter = new IBKRActivityAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testDashOnlyFieldInRevolutQuantity(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . "2024-01-15,AAPL,BUY,-,150.00,1500.00,USD,1.0\n";

        $adapter = new RevolutStocksAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testDashOnlyFieldInDegiroQuantity(): void
    {
        $csv = "Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,,Price,,Local value,,Value,,Exchange rate,Transaction costs,,Total,,Order ID\n"
            . "15-01-2024,10:30,Apple Inc,US0378331005,NASDAQ,XNAS,-,,150.00,USD,-150.00,USD,-150.00,USD,1.0,-1.50,USD,-151.50,USD,order123\n";

        $adapter = new DegiroTransactionsAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    // ---------------------------------------------------------------------------
    // Scenario — DegiroAccountStatementAdapter: adversarial inputs
    // ---------------------------------------------------------------------------

    public function testDegiroAccountStatementMixedLineEndings(): void
    {
        $csv = "Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID\r\n"
            . "15-01-2024,10:30,Apple Inc,US0378331005,Dividend,,100.00,USD,100.00,USD,order1\r"
            . "15-01-2024,10:35,Apple Inc,US0378331005,Dividendbelasting,,-15.00,USD,85.00,USD,order2\n";

        $adapter = new DegiroAccountStatementAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testDegiroAccountStatementSqlInjectionInDescription(): void
    {
        $csv = "Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID\n"
            . "15-01-2024,10:30,Apple Inc,US0378331005,\"Dividend'); DROP TABLE users;--\",,100.00,USD,100.00,USD,order1\n";

        $adapter = new DegiroAccountStatementAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }

    public function testDegiroAccountStatementTruncatedRows(): void
    {
        $header = "Date,Time,Product,ISIN,Description,FX,Change,,Balance,,Order ID\n";
        $rows = [
            "15-01-2024,10:30,Apple Inc,US0378331005,Dividend,,100.00,USD,100.00,USD,order1\n",
            "15-01-2024,10:30,Apple Inc\n",   // truncated — missing from ISIN onward
            "15-01-2024\n",                   // only date
            "\n",                             // empty row
        ];

        $adapter = new DegiroAccountStatementAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($header . implode('', $rows)));
    }

    // ---------------------------------------------------------------------------
    // Scenario 10: Degiro valid header + truncated data rows (progressively fewer columns)
    // ---------------------------------------------------------------------------

    public function testDegiroTruncatedDataRows(): void
    {
        // Full header has 20 columns. Rows below have progressively fewer.
        $header = "Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,,Price,,Local value,,Value,,Exchange rate,Transaction costs,,Total,,Order ID\n";

        $rows = [
            // All columns present
            "15-01-2024,10:30,Apple Inc,US0378331005,NASDAQ,XNAS,10,,150.00,USD,1500.00,USD,1500.00,USD,1.0,-1.50,USD,1498.50,USD,order1\n",
            // Missing last 5 columns
            "15-01-2024,10:30,Apple Inc,US0378331005,NASDAQ,XNAS,10,,150.00,USD,1500.00,USD,1500.00\n",
            // Missing from Price onward
            "15-01-2024,10:30,Apple Inc,US0378331005\n",
            // Only date and time
            "15-01-2024,10:30\n",
            // Single field
            "15-01-2024\n",
            // Empty row (should be skipped)
            "\n",
        ];

        $csv = $header . implode('', $rows);

        $adapter = new DegiroTransactionsAdapter();
        $this->assertParseReturnsResult(fn () => $adapter->parse($csv));
    }
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Asserts that parse() either returns a valid ParseResult or throws one of the
     * explicitly allowed domain/validation exceptions.
     *
     * Allowed exceptions:
     *   - \InvalidArgumentException — validation, unsupported currency, bad date
     *   - \Brick\Math\Exception\NumberFormatException — malformed numeric field
     *   - \DomainException — covers \App\BrokerImport\Domain\Exception\* subclasses
     *
     * NOT caught (indicate adapter bugs, not input validation failures):
     *   - \TypeError — wrong type passed internally
     *   - \RuntimeException — unexpected runtime state (e.g. memory limit, I/O error)
     *   - \LogicException — programming error
     */
    private function assertParseReturnsResult(callable $fn): void
    {
        try {
            $result = $fn();
            self::assertInstanceOf(ParseResult::class, $result);
        } catch (\InvalidArgumentException) {
            // Allowed: validation / unsupported currency / bad date
        } catch (\Brick\Math\Exception\NumberFormatException) {
            // Allowed: malformed numeric field
        } catch (\DomainException) {
            // Covers \App\BrokerImport\Domain\Exception\* which extend \DomainException
        }
    }
}
