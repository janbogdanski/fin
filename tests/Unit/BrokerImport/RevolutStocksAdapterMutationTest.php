<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for RevolutStocksAdapter.
 * Targets: priority(), line numbers, type map, commission resolution,
 * symbol fallback, currency, parse date formats.
 */
final class RevolutStocksAdapterMutationTest extends TestCase
{
    private RevolutStocksAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new RevolutStocksAdapter();
    }

    public function testPriorityReturnsExactly50(): void
    {
        self::assertSame(50, $this->adapter->priority());
    }

    /**
     * Kills line number mutants in error path: $lineNumber = $i + 1.
     */
    public function testErrorLineNumberIsCorrect(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . "2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00\n"
            . 'invalid-date,MSFT,SELL,5,415.20,2076.00,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills warning line number for unknown type.
     */
    public function testWarningLineNumberForUnknownType(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . "2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,1.00\n"
            . '2024-03-16,MSFT,TRANSFER,5,415.20,2076.00,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);

        $typeWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'Unknown transaction type'),
        );
        self::assertCount(1, $typeWarnings);
        $warning = array_values($typeWarnings)[0];
        self::assertSame(3, $warning->lineNumber);
    }

    /**
     * Kills STOCK SPLIT type mapping.
     */
    public function testParsesStockSplitType(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . '2024-06-10,AAPL,STOCK SPLIT,0,0.00,0.00,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame(TransactionType::CORPORATE_ACTION, $result->transactions[0]->type);
    }

    /**
     * Kills resolveCommission: non-numeric commission defaults to zero.
     */
    public function testNonNumericCommissionDefaultsToZero(): void
    {
        $csv = "Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission\n"
            . '2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,COMPLETED,N/A';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->isZero());
    }

    /**
     * Kills resolveCommission: negative commission becomes absolute value.
     */
    public function testNegativeCommissionBecomesPositive(): void
    {
        $csv = "Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission\n"
            . '2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,COMPLETED,-2.50';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('2.50'));
    }

    /**
     * Kills symbol fallback: Symbol column (newer format).
     */
    public function testUsesSymbolColumnInNewerFormat(): void
    {
        $csv = "Date,Symbol,Type,Quantity,Price,Amount,Currency,State,Commission\n"
            . '2024-03-15,AAPL,BUY,10,171.25,1712.50,USD,COMPLETED,0.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('AAPL', $result->transactions[0]->symbol);
    }

    /**
     * Kills FalseValue mutant: strtok returns false guard.
     */
    public function testSupportsReturnsFalseForNewlineOnly(): void
    {
        self::assertFalse($this->adapter->supports("\n", 'revolut.csv'));
    }

    /**
     * Kills error rawData sanitization mutant.
     */
    public function testErrorRawDataIsSanitized(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . 'invalid-date,=CMD("calc"),BUY,10,171.25,1712.50,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertNotNull($result->errors[0]->rawData);

        foreach ($result->errors[0]->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }

    /**
     * Kills Y-m-d H:i:s datetime format parsing.
     */
    public function testParsesDateTimeWithTime(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . '2024-03-15 14:30:00,AAPL,BUY,10,171.25,1712.50,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2024-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    /**
     * Kills unresolvedTickers count mutant.
     */
    public function testUnresolvedTickerCountInWarning(): void
    {
        $csv = "Date,Ticker,Type,Quantity,Price per share,Total Amount,Currency,FX Rate\n"
            . "2024-03-15,XYZABC,BUY,10,100.00,1000.00,USD,1.00\n"
            . "2024-03-16,DEFGHI,BUY,5,50.00,250.00,USD,1.00\n"
            . '2024-03-17,JKLMNO,SELL,3,60.00,180.00,USD,1.00';

        $result = $this->adapter->parse($csv);

        self::assertCount(3, $result->transactions);

        $isinWarnings = array_filter(
            $result->warnings,
            static fn ($w) => str_contains($w->message, 'ISIN not available'),
        );
        self::assertCount(1, $isinWarnings);

        $warning = array_values($isinWarnings)[0];
        self::assertStringContainsString('3 ticker(s)', $warning->message);
    }
}
