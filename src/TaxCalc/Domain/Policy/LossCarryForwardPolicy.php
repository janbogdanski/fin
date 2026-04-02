<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Policy;

use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\RoundingMode;

/**
 * Polityka rozliczania strat z lat ubieglych.
 *
 * Art. 9 ust. 3 ustawy o PIT:
 * - Strate mozna odliczyc w ciagu 5 kolejnych lat podatkowych
 * - W jednym roku mozna odliczyc max 50% kwoty straty
 * - Kryptowaluty maja ODREBNY koszyk strat (art. 30b ust. 5a-5g)
 *
 * Ta klasa NIE rekomenduje kwoty do odliczenia (to byloby doradztwo podatkowe).
 * Zwraca jedynie dopuszczalny zakres (0 do max).
 *
 * @see art. 9 ust. 3 ustawy z dnia 26 lipca 1991 r. o podatku dochodowym od osob fizycznych
 */
final class LossCarryForwardPolicy
{
    /**
     * Max 5 lat na rozliczenie straty
     */
    private const int CARRY_FORWARD_YEARS = 5;

    /**
     * Max 50% straty w jednym roku
     */
    private const string MAX_YEARLY_RATIO = '0.50';

    private function __construct()
    {
    }

    /**
     * Oblicza dopuszczalny zakres odliczenia dla danej straty w danym roku.
     *
     * @return LossDeductionRange|null null jezeli strata wygasla lub nie ma czego odliczac
     */
    public static function calculateRange(
        PriorYearLoss $loss,
        TaxYear $currentYear,
    ): ?LossDeductionRange {
        if ($loss->remainingAmount->isZero()) {
            return null;
        }

        $expiresInYear = TaxYear::of($loss->taxYear->value + self::CARRY_FORWARD_YEARS);
        $yearsRemaining = $expiresInYear->value - $currentYear->value;

        if ($yearsRemaining < 0) {
            return null;
        }

        if ($currentYear->value <= $loss->taxYear->value) {
            throw new \InvalidArgumentException(
                "Current year ({$currentYear->value}) must be after loss year ({$loss->taxYear->value})",
            );
        }

        $fiftyPercentOfOriginal = $loss->originalAmount
            ->multipliedBy(self::MAX_YEARLY_RATIO)
            ->toScale(2, RoundingMode::DOWN);

        $maxDeduction = $fiftyPercentOfOriginal->isLessThan($loss->remainingAmount)
            ? $fiftyPercentOfOriginal
            : $loss->remainingAmount;

        return new LossDeductionRange(
            taxCategory: $loss->taxCategory,
            lossYear: $loss->taxYear,
            originalAmount: $loss->originalAmount,
            remainingAmount: $loss->remainingAmount,
            maxDeductionThisYear: $maxDeduction,
            expiresInYear: $expiresInYear,
            yearsRemaining: $yearsRemaining,
        );
    }
}
