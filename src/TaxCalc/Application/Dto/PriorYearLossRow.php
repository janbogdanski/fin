<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Dto;

/**
 * Read-side DTO for prior year losses displayed in the UI.
 * Replaces untyped array from PriorYearLossCrudPort::findByUser().
 */
final readonly class PriorYearLossRow
{
    public function __construct(
        public string $id,
        public int $lossYear,
        public string $taxCategory,
        public string $originalAmount,
        public string $remainingAmount,
        public string $createdAt,
    ) {
    }
}
