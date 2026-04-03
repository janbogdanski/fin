<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for CsvSanitizer trait.
 * Targets: index checks ($value[1] vs $value[0] or $value[2]),
 * LogicalAnd condition, dot-after-dash preservation.
 *
 * Uses BossaHistoryAdapter as concrete host of the CsvSanitizer trait.
 */
final class CsvSanitizerMutationTest extends TestCase
{
    private BossaHistoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new BossaHistoryAdapter();
    }

    /**
     * Kills DecrementInteger mutant: isset($value[1]) changed to isset($value[0]).
     * A single "-" character: strlen=1, $value[1] is NOT set.
     * With the mutant (isset($value[0])), it WOULD be set (always true for non-empty).
     * So "-" should be stripped, not preserved.
     */
    public function testSingleDashIsStripped(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;-;K;10;350,00;3500,00;15,50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        // The "-" should be stripped by sanitize, not preserved as a "negative number"
        self::assertSame('', $result->transactions[0]->symbol);
    }

    /**
     * Kills IncrementInteger mutant: isset($value[1]) changed to isset($value[2]).
     * "-5" has length 2: $value[1]='5' is set, $value[2] is NOT set.
     * With the mutant, "-5" would not be recognized as a number and would be stripped.
     */
    public function testShortNegativeNumberIsPreserved(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;CDR;K;10;350,00;3500,00;-5;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        // Commission "-5" -> abs = 5
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('5'));
    }

    /**
     * Kills DecrementInteger mutant on second check: $value[1] === '.' changed to $value[0] === '.'.
     * "-." should be preserved (starts with dash, $value[1] is '.').
     * With mutant ($value[0] === '.'), $value[0] is '-', not '.', so it would NOT be preserved.
     */
    public function testDashDotPatternIsPreserved(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;CDR;K;10;350,00;3500,00;-.50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        // "-.50" is a negative number, sanitizer preserves it, abs() -> 0.50
        self::assertTrue($result->transactions[0]->commission->amount()->isEqualTo('0.50'));
    }

    /**
     * Kills LogicalAnd mutant:
     * str_starts_with($value, '-') && isset($value[1]) && (...)
     * changed to:
     * (str_starts_with($value, '-') || isset($value[1])) && (...)
     *
     * With the mutant, "=5" (doesn't start with '-') would still enter the
     * "preserve" branch IF isset("=5"[1]) is true (which it is) AND "=5"[1]==='5' (ctype_digit -> true).
     * So "=5" would be preserved (NOT stripped). This test checks that "=5" IS stripped.
     */
    public function testFormulaWithDigitIsStripped(): void
    {
        $csv = "Data operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;=5;K;10;350,00;3500,00;15,50;PLN';

        $result = $this->adapter->parse($csv);

        self::assertCount(1, $result->transactions);
        // "=5" should be sanitized: ltrim strips '=', leaving '5'
        self::assertSame('5', $result->transactions[0]->symbol);
    }

    /**
     * Kills BOM stripping mutant.
     */
    public function testBomIsStripped(): void
    {
        $csv = "\xEF\xBB\xBFData operacji;Instrument;Strona;Ilość;Kurs;Wartość;Prowizja;Waluta\n"
            . '2024-03-15;CDR;K;10;350,00;3500,00;15,50;PLN';

        // supports() should work with BOM
        self::assertTrue($this->adapter->supports($csv, 'bossa.csv'));

        $result = $this->adapter->parse($csv);
        self::assertCount(1, $result->transactions);
    }
}
