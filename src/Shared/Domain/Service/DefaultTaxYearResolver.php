<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Business rule: before the PIT filing deadline (May 1), default to
 * the previous year. From May 1 onward, default to the current year.
 */
final readonly class DefaultTaxYearResolver
{
    private const int FILING_DEADLINE_MONTH = 5;

    public function resolve(\DateTimeImmutable $now): int
    {
        $currentYear = (int) $now->format('Y');
        $month = (int) $now->format('n');

        return $month < self::FILING_DEADLINE_MONTH ? $currentYear - 1 : $currentYear;
    }
}
