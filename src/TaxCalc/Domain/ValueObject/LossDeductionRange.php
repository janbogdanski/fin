<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\ValueObject;

use Brick\Math\BigDecimal;

/**
 * Zakres mozliwego odliczenia straty w danym roku podatkowym.
 *
 * NIE rekomenduje konkretnej kwoty (to byloby doradztwo podatkowe).
 * Zwraca zakres: od 0 do maxDeductionThisYear.
 *
 * @see art. 9 ust. 3 ustawy o PIT
 */
final readonly class LossDeductionRange
{
    public function __construct(
        public TaxCategory $taxCategory,
        public TaxYear $lossYear,
        public BigDecimal $originalAmount,
        public BigDecimal $remainingAmount,
        public BigDecimal $maxDeductionThisYear,
        public TaxYear $expiresInYear,
        public int $yearsRemaining,
    ) {
        if ($originalAmount->isNegative()) {
            throw new \InvalidArgumentException('originalAmount must not be negative');
        }

        if ($remainingAmount->isNegative()) {
            throw new \InvalidArgumentException('remainingAmount must not be negative');
        }

        if ($remainingAmount->isGreaterThan($originalAmount)) {
            throw new \InvalidArgumentException('remainingAmount must not exceed originalAmount');
        }

        if ($maxDeductionThisYear->isNegative()) {
            throw new \InvalidArgumentException('maxDeductionThisYear must not be negative');
        }

        if ($maxDeductionThisYear->isGreaterThan($remainingAmount)) {
            throw new \InvalidArgumentException('maxDeductionThisYear must not exceed remainingAmount');
        }

        if ($yearsRemaining < 0) {
            throw new \InvalidArgumentException('yearsRemaining must not be negative');
        }
    }
}
