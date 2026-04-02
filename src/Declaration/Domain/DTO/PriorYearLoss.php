<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Strata z lat poprzednich do odliczenia.
 */
final readonly class PriorYearLoss
{
    public function __construct(
        public int $year,
        public string $amount,
        public string $deducted,
    ) {
    }
}
