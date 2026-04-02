<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Command;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Command — zainicjuj obliczenie rocznego podatku.
 * Readonly DTO (CQRS write side).
 */
final readonly class CalculateAnnualTax
{
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {
    }
}
