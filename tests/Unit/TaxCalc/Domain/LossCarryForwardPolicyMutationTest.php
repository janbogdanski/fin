<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Policy\LossCarryForwardPolicy;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for LossCarryForwardPolicy.
 *
 * Targets: DecrementInteger/IncrementInteger on CARRY_FORWARD_YEARS (5),
 * boundary conditions for year expiration.
 */
final class LossCarryForwardPolicyMutationTest extends TestCase
{
    /**
     * Kills DecrementInteger on CARRY_FORWARD_YEARS (5->4).
     * Loss from 2020, year 2025 = exactly 5 years later. Should be valid with CARRY_FORWARD_YEARS=5.
     * With 4, expiresInYear would be 2024, yearsRemaining = -1, would return null.
     */
    public function testLossExactlyFiveYearsAgoIsStillValid(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2020),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('5000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2025));

        self::assertNotNull($range, 'Loss from exactly 5 years ago should still be deductible');
        self::assertSame(0, $range->yearsRemaining);
        self::assertTrue($range->expiresInYear->equals(TaxYear::of(2025)));
    }

    /**
     * Kills IncrementInteger on CARRY_FORWARD_YEARS (5->6).
     * Loss from 2019, year 2025 = 6 years later. Should be expired with CARRY_FORWARD_YEARS=5.
     * With 6, expiresInYear would be 2025, yearsRemaining = 0, would return range instead of null.
     */
    public function testLossSixYearsAgoIsExpired(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2019),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('5000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2025));

        self::assertNull($range, 'Loss from 6 years ago should be expired');
    }

    /**
     * Verifies yearsRemaining calculation: expiresInYear - currentYear.
     * Loss 2022, current 2024 -> expires 2027, remaining = 3.
     */
    public function testYearsRemainingCalculatedCorrectly(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2022),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('10000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        // expiresInYear = 2022 + 5 = 2027
        // yearsRemaining = 2027 - 2024 = 3
        self::assertSame(3, $range->yearsRemaining);
    }
}
