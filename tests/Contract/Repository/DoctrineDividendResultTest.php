<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Infrastructure\Doctrine\DoctrineDividendResultQueryAdapter;
use App\TaxCalc\Infrastructure\Doctrine\DoctrineDividendResultRepository;

final class DoctrineDividendResultTest extends DividendResultContractTestCase
{
    protected function createRepository(): DividendResultRepositoryPort
    {
        return self::getContainer()->get(DoctrineDividendResultRepository::class);
    }

    protected function createQuery(): DividendResultQueryPort
    {
        return self::getContainer()->get(DoctrineDividendResultQueryAdapter::class);
    }
}
