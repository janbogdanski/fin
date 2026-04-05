<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

/**
 * Business rules for prior year loss carry-forward validation.
 *
 * @see art. 9 ust. 3 ustawy o PIT
 */
final class PriorYearLossRules
{
    /**
     * Max 5 years for carry-forward (art. 9 ust. 3 ustawy o PIT).
     */
    public const int CARRY_FORWARD_YEARS = 5;

    /**
     * AC5: Check if loss year has expired (older than 5 years).
     */
    public static function isLossYearExpired(int $lossYear, int $currentYear): bool
    {
        return $lossYear < $currentYear - self::CARRY_FORWARD_YEARS;
    }

    /**
     * Loss year must be strictly before the current year (prior-year loss).
     */
    public static function isLossYearInvalid(int $lossYear, int $currentYear): bool
    {
        return $lossYear >= $currentYear;
    }
}
