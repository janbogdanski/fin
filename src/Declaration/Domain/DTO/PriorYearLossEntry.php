<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Strata z lat poprzednich do odliczenia (wpis w raporcie audytowym).
 *
 * Renamed from PriorYearLoss to avoid naming collision with
 * TaxCalc\Domain\ValueObject\PriorYearLoss (P2-006).
 */
final readonly class PriorYearLossEntry
{
    public function __construct(
        public int $year,
        public string $amount,
        public string $deducted,
    ) {
    }
}
