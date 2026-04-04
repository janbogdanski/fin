<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Policy\LossCarryForwardPolicy;
use App\TaxCalc\Domain\Policy\TaxRoundingPolicy;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\TestCase;
use App\Tests\Factory\ClosedPositionMother;

/**
 * Property-based tests for tax calculation invariants — P2-068 batch.
 *
 * Covers equityTax computation, non-negativity, loss deduction flooring,
 * and the art. 9 ust. 3 50%-cap on loss carry-forward.
 */
final class TaxCalcPropertiesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Property 1: equityTax = round(max(equityGainLoss, 0) * 0.19) to full PLN
    //             rounded per art. 63 (HALF_UP to scale 0)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider equityTaxFormulaProvider
     */
    public function testEquityTaxEqualsGainLossTimesRate(int $seed): void
    {
        mt_srand($seed);

        // Generate a random positive gain (ensures tax > 0 for clearer assertion)
        $gainCents = mt_rand(100, 9_999_999); // 1.00 to 99999.99 PLN
        $gainStr   = sprintf('%d.%02d', intdiv($gainCents, 100), $gainCents % 100);

        $closedPosition = ClosedPositionMother::withGain($gainStr);

        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));
        $calc->addClosedPositions([$closedPosition], TaxCategory::EQUITY);
        $snapshot = $calc->finalize();

        // Expected follows AnnualTaxCalculation::finalize() two-step path:
        // 1. Round taxable income (gain) to full PLN per art. 63
        // 2. Multiply by 0.19 and round result to full PLN
        $taxBase     = TaxRoundingPolicy::roundTaxBase(BigDecimal::of($gainStr));
        $expectedTax = TaxRoundingPolicy::roundTax($taxBase->multipliedBy('0.19'));

        self::assertTrue(
            $snapshot->equityTax->isEqualTo($expectedTax),
            "Seed {$seed}: equityTax={$snapshot->equityTax} != expected={$expectedTax} for gain={$gainStr}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function equityTaxFormulaProvider(): array
    {
        return array_map(fn (int $i) => [$i + 11000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 2: equityTax is always >= 0 — losses never produce negative tax
    // -------------------------------------------------------------------------

    /**
     * @dataProvider taxNonNegativeProvider
     */
    public function testEquityTaxIsNeverNegative(int $seed): void
    {
        mt_srand($seed);

        // Mix of gains and losses — net result may be negative
        $numPositions = mt_rand(1, 6);
        $positions    = [];

        for ($i = 0; $i < $numPositions; $i++) {
            $isGain = (bool) mt_rand(0, 1);
            $amount = sprintf('%d.%02d', mt_rand(1, 5000), mt_rand(0, 99));

            $positions[] = $isGain
                ? ClosedPositionMother::withGain($amount)
                : ClosedPositionMother::withLoss($amount);
        }

        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));
        $calc->addClosedPositions($positions, TaxCategory::EQUITY);
        $snapshot = $calc->finalize();

        self::assertFalse(
            $snapshot->equityTax->isNegative(),
            "Seed {$seed}: equityTax={$snapshot->equityTax} is negative (losses must not generate negative tax)",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function taxNonNegativeProvider(): array
    {
        return array_map(fn (int $i) => [$i + 12000], range(0, 99));
    }

    // -------------------------------------------------------------------------
    // Property 3: equityTaxableIncome >= 0 even when prior-year loss deduction
    //             exceeds equityGainLoss (taxable income floors at 0, never negative)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider lossDeductionFloorProvider
     */
    public function testTaxableIncomeFloorsAtZeroWhenLossExceedsGain(int $seed): void
    {
        mt_srand($seed);

        // Create a gain position
        $gainCents = mt_rand(100, 100_000);
        $gainStr   = sprintf('%d.%02d', intdiv($gainCents, 100), $gainCents % 100);

        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));
        $calc->addClosedPositions(
            [ClosedPositionMother::withGain($gainStr)],
            TaxCategory::EQUITY,
        );

        // Apply a prior-year loss deduction LARGER than the gain
        $gain              = BigDecimal::of($gainStr);
        $excessLoss        = $gain->plus(BigDecimal::of((string) mt_rand(1, 10000)));
        $excessLossRounded = $excessLoss->toScale(2, RoundingMode::HALF_UP);

        // Build a LossDeductionRange that allows deducting the excess
        $range = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2020),
            originalAmount: $excessLossRounded->multipliedBy('3'),
            remainingAmount: $excessLossRounded->multipliedBy('2'),
            maxDeductionThisYear: $excessLossRounded,
            expiresInYear: TaxYear::of(2025),
            yearsRemaining: 0,
        );

        $calc->applyPriorYearLosses([$range], [$excessLossRounded]);
        $snapshot = $calc->finalize();

        self::assertFalse(
            $snapshot->equityTaxableIncome->isNegative(),
            "Seed {$seed}: equityTaxableIncome={$snapshot->equityTaxableIncome} is negative (must floor at 0)",
        );

        self::assertTrue(
            $snapshot->equityTaxableIncome->isZero(),
            "Seed {$seed}: equityTaxableIncome={$snapshot->equityTaxableIncome} should be 0 when deduction > gain",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function lossDeductionFloorProvider(): array
    {
        return array_map(fn (int $i) => [$i + 13000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 4: LossCarryForwardPolicy: maxDeductionThisYear <= 50% of originalAmount
    //             Art. 9 ust. 3 cap — max 50% of the original loss in one year
    // -------------------------------------------------------------------------

    /**
     * @dataProvider lossCarryForwardCapProvider
     */
    public function testMaxDeductionNeverExceedsFiftyPercentOfOriginal(int $seed): void
    {
        mt_srand($seed);

        $originalCents = mt_rand(200, 9_999_999); // at least 2.00 so 50% >= 0.01
        $originalStr   = sprintf('%d.%02d', intdiv($originalCents, 100), $originalCents % 100);
        $original      = BigDecimal::of($originalStr);

        // remainingAmount in [1, original]
        $remainingCents = mt_rand(1, $originalCents);
        $remainingStr   = sprintf('%d.%02d', intdiv($remainingCents, 100), $remainingCents % 100);
        $remaining      = BigDecimal::of($remainingStr);

        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2021),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: $original,
            remainingAmount: $remaining,
        );

        $currentYear = TaxYear::of(2024);
        $range       = LossCarryForwardPolicy::calculateRange($loss, $currentYear);

        if ($range === null) {
            // Expired or zero remaining — skip this iteration
            self::assertTrue(true, 'Skipped: range is null (loss expired or zero remaining)');
            return;
        }

        // 50% of original, rounded DOWN per policy
        $cap = $original->multipliedBy('0.50')->toScale(2, RoundingMode::DOWN);

        self::assertTrue(
            $range->maxDeductionThisYear->isLessThanOrEqualTo($cap),
            "Seed {$seed}: maxDeductionThisYear={$range->maxDeductionThisYear} exceeds 50% cap={$cap} for original={$originalStr}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function lossCarryForwardCapProvider(): array
    {
        return array_map(fn (int $i) => [$i + 14000], range(0, 99));
    }
}
