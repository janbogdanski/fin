<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for DegiroTransactionsAdapter.
 * Targets: priority(), line numbers, supports() logical conditions,
 * resolveColumnMapping, parseCommission, resolveCurrency, Dutch headers.
 */
final class DegiroTransactionsAdapterMutationTest extends TestCase
{
    private DegiroTransactionsAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new DegiroTransactionsAdapter();
    }

    public function testPriorityReturnsExactly50(): void
    {
        self::assertSame(50, $this->adapter->priority());
    }

    /**
     * Kills line number mutants: $lineNumber = $i + 1.
     */
    public function testErrorLineNumberIsCorrect(): void
    {
        $csv = $this->buildCsv(
            "15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123\n"
            . 'invalid-date,14:30,MSFT,US5949181045,NASDAQ,XNAS,5,400.00,USD,-2000.00,USD,-1845.00,EUR,1.084,-1.00,EUR,-1846.00,EUR,def456',
        );

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertSame(3, $result->errors[0]->lineNumber);
    }

    /**
     * Kills missing column error lineNumber: ParseError(lineNumber: 1, ...).
     */
    public function testMissingColumnsErrorHasLineNumber1(): void
    {
        $csv = "Date,Time,Product\n15-03-2025,14:30,APPLE INC";

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertSame(1, $result->errors[0]->lineNumber);
    }

    /**
     * Kills supports() LogicalAnd/Or mutations on hasEnglishTransactionHeaders.
     * Tests a header that has Date, Time, ISIN, Product but NOT Quantity or Exchange rate.
     */
    public function testDoesNotSupportHeaderWithOnlyDateTimeIsinProduct(): void
    {
        $content = "Date,Time,Product,ISIN,Description,Change,Balance\n10-05-2025,08:00,APPLE INC,US0378331005,Dividend,2.50,1502.50";

        // This is Account Statement format, not Transactions
        self::assertFalse($this->adapter->supports($content, 'account.csv'));
    }

    /**
     * Kills Dutch header detection. The adapter should parse Dutch format.
     */
    public function testParsesDutchFormat(): void
    {
        $csv = "Datum,Tijd,Product,ISIN,Beurs,Uitvoeringsplaats,Aantal,Koers,,Lokale waarde,,Waarde,,Wisselkoers,Transactiekosten,,Totaal,,Order ID\n"
            . '15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertCount(0, $result->errors);

        $tx = $result->transactions[0];
        self::assertSame(TransactionType::BUY, $tx->type);
        self::assertSame('APPLE INC', $tx->symbol);
        self::assertTrue($tx->quantity->isEqualTo('10'));
        self::assertTrue($tx->pricePerUnit->amount()->isEqualTo('171.25'));
        self::assertSame('USD', $tx->pricePerUnit->currency()->value);
    }

    /**
     * Kills resolveColumnMapping mutants for NL detection.
     * Ensures Dutch column detection works separately from English.
     */
    public function testSupportsDutchFormat(): void
    {
        $content = "Datum,Tijd,Product,ISIN,Beurs,Uitvoeringsplaats,Aantal,Koers\n15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25";

        self::assertTrue($this->adapter->supports($content, 'transacties.csv'));
    }

    /**
     * Kills unrecognized column format error path.
     */
    public function testUnrecognizedColumnFormatReturnsError(): void
    {
        $csv = "Foo,Bar,Baz,Qux\n1,2,3,4";

        $result = $this->adapter->parse($csv);

        self::assertCount(0, $result->transactions);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Unable to detect', $result->errors[0]->message);
    }

    /**
     * Kills parseCommission: empty string returns '0'.
     */
    public function testEmptyCommissionDefaultsToZero(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('0'));
    }

    /**
     * Kills parseCommission: negative commission takes absolute value.
     */
    public function testNegativeCommissionBecomesPositive(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-5.50,EUR,-1586.92,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('5.50'));
    }

    /**
     * Kills resolveCurrency: default to EUR when all candidates are empty.
     */
    public function testDefaultCurrencyIsEUR(): void
    {
        // Build CSV without currency columns (all empty after value columns)
        $csv = "Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price,Local value,Value,Exchange rate,Transaction costs,Total,Order ID\n"
            . '15-03-2025,14:30,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,-1712.50,-1580.42,1.0836,-1.00,-1581.42,abc123';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('EUR', $result->transactions[0]->pricePerUnit->currency()->value);
    }

    /**
     * Kills tryParseISIN: empty ISIN returns null.
     */
    public function testEmptyISINReturnsNull(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertNull($result->transactions[0]->isin);
    }

    /**
     * Kills tryParseISIN: invalid ISIN returns null (not exception).
     */
    public function testInvalidISINReturnsNull(): void
    {
        $csv = $this->buildCsv('15-03-2025,14:30,APPLE INC,INVALID_ISIN,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertNull($result->transactions[0]->isin);
    }

    /**
     * Kills parseDateTime fallback: date-only format (no time).
     */
    public function testParsesDateWithoutTime(): void
    {
        $csv = $this->buildCsv('15-03-2025,,APPLE INC,US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        self::assertSame('2025-03-15', $result->transactions[0]->date->format('Y-m-d'));
    }

    /**
     * Kills error rawData sanitization mutant.
     */
    public function testErrorRawDataIsSanitized(): void
    {
        $csv = $this->buildCsv('invalid-date,14:30,=CMD("calc"),US0378331005,NASDAQ,XNAS,10,171.25,USD,-1712.50,USD,-1580.42,EUR,1.0836,-1.00,EUR,-1581.42,EUR,abc123');

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->errors);
        self::assertNotNull($result->errors[0]->rawData);

        foreach ($result->errors[0]->rawData as $value) {
            self::assertStringStartsNotWith('=', $value);
        }
    }

    /**
     * Kills FalseValue mutant: strtok returns false guard.
     */
    public function testSupportsReturnsFalseForNewlineOnly(): void
    {
        self::assertFalse($this->adapter->supports("\n", 'transactions.csv'));
    }

    /**
     * Kills isIBKRFormat check: header containing 'Statement'.
     */
    public function testDoesNotSupportHeaderWithStatement(): void
    {
        $content = "Statement,Date,Time,Product,ISIN,Quantity,Price\n";
        self::assertFalse($this->adapter->supports($content, 'transactions.csv'));
    }

    private function buildCsv(string $dataRows): string
    {
        $header = 'Date,Time,Product,ISIN,Exchange,Execution Venue,Quantity,Price,,Local value,,Value,,Exchange rate,Transaction costs,,Total,,Order ID';

        return $header . "\n" . $dataRows;
    }
}
