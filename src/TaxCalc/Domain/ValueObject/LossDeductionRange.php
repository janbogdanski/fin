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
    }
}
