<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Dto;

use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;

/**
 * Read-side DTO for prior year losses displayed in the UI.
 */
final readonly class PriorYearLossRow
{
    /**
     * @param list<int> $usedInYears Tax years in which this loss was applied as a deduction.
     *                               Non-empty means the loss is locked — delete and amount reduction are blocked.
     */
    public function __construct(
        public string $id,
        public int $lossYear,
        public TaxCategory $taxCategory,
        public BigDecimal $originalAmount,
        public BigDecimal $remainingAmount,
        public \DateTimeImmutable $createdAt,
        public array $usedInYears = [],
    ) {
    }

    public function isUsedInAnyYear(): bool
    {
        return $this->usedInYears !== [];
    }
}
