<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Command;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;

/**
 * Command DTO for saving (creating or updating) a prior year loss entry.
 *
 * Replaces the 4-parameter signature of PriorYearLossCrudPort::save()
 * with a single typed value object, reducing call-site fragility.
 */
final readonly class SavePriorYearLoss
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $lossYear,
        public readonly TaxCategory $taxCategory,
        public readonly BigDecimal $amount,
    ) {
    }
}
