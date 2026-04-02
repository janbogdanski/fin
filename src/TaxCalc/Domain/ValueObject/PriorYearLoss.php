<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\ValueObject;

use Brick\Math\BigDecimal;

/**
 * Strata z lat ubieglych do rozliczenia.
 *
 * @see art. 9 ust. 3 ustawy o PIT
 */
final readonly class PriorYearLoss
{
    public function __construct(
        public TaxYear $taxYear,
        public TaxCategory $taxCategory,
        public BigDecimal $originalAmount,
        public BigDecimal $remainingAmount,
    ) {
        if ($originalAmount->isNegative()) {
            throw new \InvalidArgumentException(
                "Original loss amount must be non-negative, got: {$originalAmount}",
            );
        }

        if ($remainingAmount->isNegative()) {
            throw new \InvalidArgumentException(
                "Remaining loss amount must be non-negative, got: {$remainingAmount}",
            );
        }

        if ($remainingAmount->isGreaterThan($originalAmount)) {
            throw new \InvalidArgumentException(
                "Remaining amount ({$remainingAmount}) cannot exceed original amount ({$originalAmount})",
            );
        }
    }
}
