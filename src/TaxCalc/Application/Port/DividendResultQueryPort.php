<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port wyjsciowy — dostep do wynikow podatkowych dywidend.
 * Implementacja (Doctrine, in-memory) w Infrastructure.
 */
interface DividendResultQueryPort
{
    /**
     * @return list<DividendTaxResult>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array;
}
