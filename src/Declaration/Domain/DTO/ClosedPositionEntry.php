<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Declaration-local DTO representing a closed position for audit reporting.
 *
 * Uses primitive types and strings to avoid importing TaxCalc domain models.
 * Mapped from TaxCalc\ClosedPosition in the Application layer.
 */
final readonly class ClosedPositionEntry
{
    public function __construct(
        public string $isin,
        public string $symbol,
        public string $buyDate,
        public string $sellDate,
        public string $buyBroker,
        public string $sellBroker,
        public string $quantity,
        public string $buyPricePerUnit,
        public string $buyPriceCurrency,
        public string $sellPricePerUnit,
        public string $sellPriceCurrency,
        public string $costBasisPLN,
        public string $proceedsPLN,
        public string $buyCommissionPLN,
        public string $sellCommissionPLN,
        public string $gainLossPLN,
        public string $buyNBPRate,
        public string $sellNBPRate,
    ) {
    }
}
