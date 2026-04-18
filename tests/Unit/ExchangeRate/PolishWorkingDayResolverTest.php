<?php

declare(strict_types=1);

namespace App\Tests\Unit\ExchangeRate;

use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolishWorkingDayResolverTest extends TestCase
{
    private PolishWorkingDayResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PolishWorkingDayResolver();
    }

    #[DataProvider('workingDayProvider')]
    public function testResolvesLastWorkingDayBefore(string $transactionDate, string $expectedDate): void
    {
        $result = $this->resolver->resolveLastWorkingDayBefore(
            new \DateTimeImmutable($transactionDate),
        );

        self::assertSame($expectedDate, $result->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function workingDayProvider(): iterable
    {
        // Regular weekday: Wednesday → Tuesday
        yield 'wednesday transaction' => ['2025-03-19', '2025-03-18'];

        // Monday → Friday
        yield 'monday transaction' => ['2025-03-17', '2025-03-14'];

        // Saturday → Friday
        yield 'saturday transaction' => ['2025-03-15', '2025-03-14'];

        // Sunday → Friday
        yield 'sunday transaction' => ['2025-03-16', '2025-03-14'];

        // Day after Święto Pracy (1 maja) — 2025-05-02 is Friday, 2025-05-01 is holiday → 2025-04-30
        yield 'after may day' => ['2025-05-02', '2025-04-30'];

        // Day after Christmas (27 Dec 2025 is Saturday) → 23 Dec (Tuesday)
        // 24 Dec is Wigilia (holiday since 2025), 25+26 Dec are holidays, 27 is Saturday
        yield 'after christmas weekend' => ['2025-12-27', '2025-12-23'];

        // 2 January 2026 (Friday) → 31 Dec 2025 (Wednesday) since 1 Jan is holiday
        yield 'after new year' => ['2026-01-02', '2025-12-31'];
    }

    public function testWeekdayIsWorkingDay(): void
    {
        // 2025-03-18 is Tuesday
        self::assertTrue($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-03-18')));
    }

    public function testWeekendIsNotWorkingDay(): void
    {
        // 2025-03-15 is Saturday
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-03-15')));
    }

    public function testHolidayIsNotWorkingDay(): void
    {
        // 2025-05-01 is Thursday (Święto Pracy)
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-05-01')));
    }

    public function testEasterMondayIsNotWorkingDay(): void
    {
        // Easter 2025: Sunday 20 April, Monday 21 April
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-04-21')));
    }

    public function testWhitSundayIsNotWorkingDay(): void
    {
        // Whit Sunday 2025 (Zesłanie Ducha Świętego): Easter (20 Apr) + 49 days = 8 June
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-08')));
    }

    public function testCorpusChristiIsNotWorkingDay(): void
    {
        // Corpus Christi 2025: Easter (20 Apr) + 60 days = 19 June
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2025-06-19')));
    }

    /**
     * P1-013: easter_days() works for years beyond 2037 (32-bit safety).
     * Easter 2040: Sunday 1 April, Monday 2 April.
     */
    public function testEasterMondayBeyond2037(): void
    {
        // Easter 2040: 1 April (Sunday), Easter Monday: 2 April
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2040-04-02')));
    }

    /**
     * P1-013: Corpus Christi beyond 2037.
     * Easter 2040: 1 April + 60 days = 31 May (Thursday).
     */
    public function testCorpusChristiBeyond2037(): void
    {
        self::assertFalse($this->resolver->isWorkingDay(new \DateTimeImmutable('2040-05-31')));
    }

    /**
     * P1-013: resolveLastWorkingDayBefore works for year 2050.
     */
    public function testResolvesWorkingDayFor2050(): void
    {
        // 2050-01-03 is Monday, last working day before = 2049-12-31 (Friday, not a holiday)
        $result = $this->resolver->resolveLastWorkingDayBefore(
            new \DateTimeImmutable('2050-01-03'),
        );

        self::assertSame('2049-12-31', $result->format('Y-m-d'));
    }
}
