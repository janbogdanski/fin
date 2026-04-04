<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * In-memory implementation of DividendResultRepositoryPort + DividendResultQueryPort for testing.
 */
final class InMemoryDividendResultAdapter implements DividendResultRepositoryPort, DividendResultQueryPort
{
    /** @var array<string, array<int, list<DividendTaxResult>>> */
    private array $store = [];

    public function deleteByUserAndYear(UserId $userId, TaxYear $taxYear): void
    {
        $this->store[$userId->toString()][$taxYear->value] = [];
    }

    /**
     * @param list<DividendTaxResult> $results
     */
    public function saveAll(UserId $userId, TaxYear $taxYear, array $results): void
    {
        foreach ($results as $result) {
            $this->store[$userId->toString()][$taxYear->value][] = $result;
        }
    }

    /**
     * @return list<DividendTaxResult>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        return $this->store[$userId->toString()][$taxYear->value] ?? [];
    }
}
