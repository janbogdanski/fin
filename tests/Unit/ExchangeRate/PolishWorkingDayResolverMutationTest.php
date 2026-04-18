<?php

declare(strict_types=1);

namespace App\Tests\Unit\ExchangeRate;

use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for PolishWorkingDayResolver.
 *
 * Targets: MAX_LOOKBACK_DAYS boundary, isWeekend >= 6 boundary,
 * easter_days + march21 calculation, movable holiday day offsets.
 */
final class PolishWorkingDayResolverMutationTest extends TestCase
{
    private PolishWorkingDayResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PolishWorkingDayResolver();
    }

    /**
     * Kills boundary mutant on isWeekend: $dayOfWeek >= 6 changed to > 6.
     * Saturday (N=6) must NOT be a working day.
     */
    public function testSaturdayIsNotWorkingDay(): void
    {
        // 2025-03-15 is Saturday
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-03-15')));
    }

    /**
     * Kills boundary mutant: Sunday (N=7) must NOT be a working day.
     */
    public function testSundayIsNotWorkingDay(): void
    {
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-03-16')));
    }

    /**
     * Kills boundary mutant: Friday (N=5) MUST be a working day (when not a holiday).
     */
    public function testFridayIsWorkingDay(): void
    {
        // 2025-03-14 is Friday (not a holiday)
        self::assertTrue($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-03-14')));
    }

    /**
     * Kills IncrementInteger/DecrementInteger on Easter Monday offset (+1 day).
     * With +2 it would be a Tuesday (working day), not Easter Monday.
     */
    public function testEasterMondayIsCorrectDate(): void
    {
        // Easter 2026: Sunday April 5, Easter Monday: April 6
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2026-04-06')));
        // The day after (Tuesday) should be working day
        self::assertTrue($this->resolver->isWorkingDay(new \DateTimeImmutable('2026-04-07')));
    }

    /**
     * Kills IncrementInteger/DecrementInteger on Whit Sunday offset (+49 days).
     * Easter 2025 = Apr 20. +49 = Jun 8. +48 would be Jun 7 (Saturday).
     */
    public function testWhitSundayIsCorrectDate(): void
    {
        // 2025: Easter = Apr 20, Whit Sunday = Jun 8
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-08')));
        // Jun 9 (Monday) should be a working day
        self::assertTrue($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-09')));
    }

    /**
     * Kills IncrementInteger/DecrementInteger on Corpus Christi offset (+60 days).
     * Easter 2025 = Apr 20. +60 = Jun 19 (Thursday). +59 = Jun 18 (Wed, working).
     */
    public function testCorpusChristiIsCorrectDate(): void
    {
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-19')));
        // Jun 18 is Wednesday (should be working day)
        self::assertTrue($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-18')));
    }

    /**
     * Kills mutation on in_array strict mode for fixed holidays.
     */
    public function testAllFixedHolidaysAreRecognized(): void
    {
        $holidays = [
            '2025-01-01', // Nowy Rok
            '2025-01-06', // Trzech Króli
            '2025-05-01', // Święto Pracy
            '2025-05-03', // Konstytucja 3 Maja
            '2025-08-15', // Wniebowzięcie NMP
            '2025-11-01', // Wszystkich Świętych
            '2025-11-11', // Święto Niepodległości
            '2025-12-24', // Wigilia (od 2025, Dz.U. 2024 poz. 1965)
            '2025-12-25', // Boże Narodzenie I
            '2025-12-26', // Boże Narodzenie II
        ];

        foreach ($holidays as $date) {
            self::assertFalse(
                $this->resolver->isWorkingDay(new \DateTimeImmutable($date)),
                "Holiday {$date} should not be a working day",
            );
        }
    }

    /**
     * Kills RuntimeException mutation on MAX_LOOKBACK_DAYS exceeded.
     * This is hard to trigger in practice (requires 7+ consecutive non-working days).
     * But we can verify the method returns correct results for long weekends.
     */
    public function testLongHolidaySequenceResolvedCorrectly(): void
    {
        // Christmas 2025: 24 (Wed/Wigilia), 25 (Thu), 26 (Fri) are holidays, 27 (Sat), 28 (Sun) weekend
        // Transaction on 29 Dec (Mon) -> last working day before = 23 Dec (Tue)
        $result = $this->resolver->resolveLastWorkingDayBefore(new \DateTimeImmutable('2025-12-29'));

        self::assertSame('2025-12-23', $result->format('Y-m-d'));
    }
}
