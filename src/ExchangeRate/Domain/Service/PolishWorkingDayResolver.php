<?php

declare(strict_types=1);

namespace App\ExchangeRate\Domain\Service;

/**
 * Resolves the last working day before a given date,
 * accounting for Polish public holidays and weekends.
 *
 * Art. 11a ust. 1 ustawy o PIT — kurs z ostatniego dnia roboczego
 * POPRZEDZAJĄCEGO dzień uzyskania przychodu.
 */
final readonly class PolishWorkingDayResolver
{
    private const int MAX_LOOKBACK_DAYS = 7;

    /**
     * Fixed Polish public holidays (month-day).
     *
     * @var list<string>
     */
    private const array FIXED_HOLIDAYS = [
        '01-01', // Nowy Rok
        '01-06', // Trzech Króli
        '05-01', // Święto Pracy
        '05-03', // Święto Konstytucji 3 Maja
        '08-15', // Wniebowzięcie NMP
        '11-01', // Wszystkich Świętych
        '11-11', // Święto Niepodległości
        '12-25', // Boże Narodzenie (pierwszy dzień)
        '12-26', // Boże Narodzenie (drugi dzień)
    ];

    /**
     * Returns the last working day strictly BEFORE the given date.
     *
     * @throws \RuntimeException when no working day found within MAX_LOOKBACK_DAYS
     */
    public function resolveLastWorkingDayBefore(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $candidate = $date->modify('-1 day');

        for ($i = 0; $i < self::MAX_LOOKBACK_DAYS; $i++) {
            if ($this->isWorkingDay($candidate)) {
                return $candidate;
            }

            $candidate = $candidate->modify('-1 day');
        }

        throw new \RuntimeException(sprintf(
            'No working day found within %d days before %s.',
            self::MAX_LOOKBACK_DAYS,
            $date->format('Y-m-d'),
        ));
    }

    public function isWorkingDay(\DateTimeImmutable $date): bool
    {
        if ($this->isWeekend($date)) {
            return false;
        }

        return ! $this->isPolishHoliday($date);
    }

    private function isWeekend(\DateTimeImmutable $date): bool
    {
        $dayOfWeek = (int) $date->format('N');

        return $dayOfWeek >= 6;
    }

    private function isPolishHoliday(\DateTimeImmutable $date): bool
    {
        $monthDay = $date->format('m-d');

        if (in_array($monthDay, self::FIXED_HOLIDAYS, true)) {
            return true;
        }

        return $this->isMovableHoliday($date);
    }

    /**
     * Movable Polish holidays: Easter Monday, Corpus Christi (Boże Ciało).
     * Both derived from Easter Sunday via the Computus algorithm.
     */
    private function isMovableHoliday(\DateTimeImmutable $date): bool
    {
        $year = (int) $date->format('Y');
        $easterSunday = $this->calculateEasterSunday($year);

        $easterMonday = $easterSunday->modify('+1 day');
        $corpusChristi = $easterSunday->modify('+60 days');

        $dateStr = $date->format('Y-m-d');

        return $dateStr === $easterMonday->format('Y-m-d')
            || $dateStr === $corpusChristi->format('Y-m-d');
    }

    /**
     * Anonymous Gregorian algorithm for Easter Sunday.
     */
    private function calculateEasterSunday(int $year): \DateTimeImmutable
    {
        $base = \easter_date($year);

        return (new \DateTimeImmutable())
            ->setTimestamp($base)
            ->setTime(0, 0);
    }
}
