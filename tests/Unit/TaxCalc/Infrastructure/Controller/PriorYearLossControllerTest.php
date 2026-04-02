<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Infrastructure\Controller;

use App\TaxCalc\Infrastructure\Controller\PriorYearLossController;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PriorYearLossController validation logic.
 *
 * AC5: Loss older than 5 years rejected.
 * AC4: Empty losses = no error.
 */
final class PriorYearLossControllerTest extends TestCase
{
    /**
     * AC5: Validates that losses older than 5 years are rejected.
     * For tax year 2025, losses from 2019 or earlier should be rejected.
     * The 5-year window for 2025: loss years 2020-2024.
     */
    public function testRejectsLossOlderThanFiveYears(): void
    {
        // A loss from 2019 cannot be deducted in 2025 (2019 + 5 = 2024 < 2025)
        $currentYear = (int) date('Y');
        $expiredYear = $currentYear - 6;

        self::assertTrue(
            PriorYearLossController::isLossYearExpired($expiredYear, $currentYear),
            "Loss from {$expiredYear} should be rejected for tax year {$currentYear}",
        );
    }

    /**
     * AC5: Fifth year is still valid.
     */
    public function testAcceptsLossExactlyFiveYearsOld(): void
    {
        $currentYear = (int) date('Y');
        $fiveYearsAgo = $currentYear - 5;

        self::assertFalse(
            PriorYearLossController::isLossYearExpired($fiveYearsAgo, $currentYear),
            "Loss from {$fiveYearsAgo} should still be valid for tax year {$currentYear}",
        );
    }

    /**
     * Loss from same year or future is invalid (cannot have prior-year loss from current year).
     */
    public function testRejectsLossFromCurrentOrFutureYear(): void
    {
        $currentYear = (int) date('Y');

        self::assertTrue(
            PriorYearLossController::isLossYearInvalid($currentYear, $currentYear),
            'Loss from current year should be rejected',
        );

        self::assertTrue(
            PriorYearLossController::isLossYearInvalid($currentYear + 1, $currentYear),
            'Loss from future year should be rejected',
        );
    }

    /**
     * Valid loss year range: 1 to 5 years before current year.
     */
    public function testAcceptsValidLossYearRange(): void
    {
        $currentYear = (int) date('Y');

        for ($offset = 1; $offset <= 5; $offset++) {
            $lossYear = $currentYear - $offset;

            self::assertFalse(
                PriorYearLossController::isLossYearExpired($lossYear, $currentYear),
                "Loss from {$lossYear} should be valid for {$currentYear}",
            );
            self::assertFalse(
                PriorYearLossController::isLossYearInvalid($lossYear, $currentYear),
                "Loss from {$lossYear} should not be invalid for {$currentYear}",
            );
        }
    }
}
