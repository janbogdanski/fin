<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Doctrine implementation of PriorYearLossQueryPort.
 *
 * TODO: Implement when prior year loss tracking is added
 * (requires separate table for loss carryforward records).
 * For now returns empty — no prior year loss deductions available.
 */
final readonly class DoctrinePriorYearLossQueryAdapter implements PriorYearLossQueryPort
{
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        return [];
    }
}
