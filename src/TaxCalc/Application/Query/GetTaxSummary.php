<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Query;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Query — pobierz podsumowanie podatkowe (CQRS read side).
 * Readonly DTO.
 */
final readonly class GetTaxSummary
{
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
    ) {
    }
}
