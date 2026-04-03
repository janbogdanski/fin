<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port wyjsciowy — dostep do zamknietych pozycji.
 * Implementacja (Doctrine, in-memory) w Infrastructure.
 */
interface ClosedPositionQueryPort
{
    /**
     * @return list<ClosedPosition>
     */
    public function findByUserYearAndCategory(
        UserId $userId,
        TaxYear $taxYear,
        TaxCategory $category,
    ): array;

    /**
     * Count closed positions for a user in a given tax year (all categories).
     * Used for reconciliation after import.
     */
    public function countByUserAndYear(UserId $userId, TaxYear $taxYear): int;
}
