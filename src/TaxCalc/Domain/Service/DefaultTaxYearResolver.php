<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use Psr\Clock\ClockInterface;

/**
 * Resolves the default tax year based on the current date.
 *
 * Business rule: before the PIT filing deadline (May 1), default to
 * the previous year. From May 1 onward, default to the current year.
 */
final readonly class DefaultTaxYearResolver
{
    private const FILING_DEADLINE_MONTH = 5;

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function resolve(): int
    {
        $now = $this->clock->now();
        $currentYear = (int) $now->format('Y');
        $month = (int) $now->format('n');

        return $month < self::FILING_DEADLINE_MONTH ? $currentYear - 1 : $currentYear;
    }
}
