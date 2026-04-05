<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Infrastructure\Controller;

use App\TaxCalc\Domain\Service\PriorYearLossRules;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PriorYearLossRules validation logic.
 *
 * AC5: Loss older than 5 years rejected.
 * AC4: Empty losses = no error.
 */
final class PriorYearLossControllerTest extends TestCase
{
    private const int CURRENT_YEAR = 2026;

    /**
     * AC5: Validates that losses older than 5 years are rejected.
     * For tax year 2026, losses from 2020 or earlier should be rejected.
     * The 5-year window for 2026: loss years 2021-2025.
     */
    public function testRejectsLossOlderThanFiveYears(): void
    {
        // A loss from 2020 cannot be deducted in 2026 (2020 + 5 = 2025 < 2026)
        $expiredYear = self::CURRENT_YEAR - 6;

        self::assertTrue(
            PriorYearLossRules::isLossYearExpired($expiredYear, self::CURRENT_YEAR),
            "Loss from {$expiredYear} should be rejected for tax year " . self::CURRENT_YEAR,
        );
    }

    /**
     * AC5: Fifth year is still valid.
     */
    public function testAcceptsLossExactlyFiveYearsOld(): void
    {
        $fiveYearsAgo = self::CURRENT_YEAR - 5;

        self::assertFalse(
            PriorYearLossRules::isLossYearExpired($fiveYearsAgo, self::CURRENT_YEAR),
            "Loss from {$fiveYearsAgo} should still be valid for tax year " . self::CURRENT_YEAR,
        );
    }

    /**
     * Loss from same year or future is invalid (cannot have prior-year loss from current year).
     */
    public function testRejectsLossFromCurrentOrFutureYear(): void
    {
        self::assertTrue(
            PriorYearLossRules::isLossYearInvalid(self::CURRENT_YEAR, self::CURRENT_YEAR),
            'Loss from current year should be rejected',
        );

        self::assertTrue(
            PriorYearLossRules::isLossYearInvalid(self::CURRENT_YEAR + 1, self::CURRENT_YEAR),
            'Loss from future year should be rejected',
        );
    }

    /**
     * Valid loss year range: 1 to 5 years before current year.
     */
    public function testAcceptsValidLossYearRange(): void
    {
        for ($offset = 1; $offset <= 5; $offset++) {
            $lossYear = self::CURRENT_YEAR - $offset;

            self::assertFalse(
                PriorYearLossRules::isLossYearExpired($lossYear, self::CURRENT_YEAR),
                "Loss from {$lossYear} should be valid for " . self::CURRENT_YEAR,
            );
            self::assertFalse(
                PriorYearLossRules::isLossYearInvalid($lossYear, self::CURRENT_YEAR),
                "Loss from {$lossYear} should not be invalid for " . self::CURRENT_YEAR,
            );
        }
    }
}
