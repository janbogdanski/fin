<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Write-side port for persisting computed dividend tax results.
 *
 * Dedup strategy: delete all existing results for user+year, then save fresh batch.
 * This avoids duplicate results on re-import (AC4).
 */
interface DividendResultRepositoryPort
{
    /**
     * Remove all dividend tax results for a user in a given tax year.
     * Called before saveAll() to ensure idempotent re-import.
     */
    public function deleteByUserAndYear(UserId $userId, TaxYear $taxYear): void;

    /**
     * Persist a batch of computed dividend tax results.
     *
     * @param list<DividendTaxResult> $results
     */
    public function saveAll(UserId $userId, TaxYear $taxYear, array $results): void;
}
