<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\Tests\InMemory\InMemoryDividendResultAdapter;

final class InMemoryDividendResultTest extends DividendResultContractTestCase
{
    private InMemoryDividendResultAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryDividendResultAdapter();
        parent::setUp();
    }

    protected function createRepository(): DividendResultRepositoryPort
    {
        return $this->adapter;
    }

    protected function createQuery(): DividendResultQueryPort
    {
        return $this->adapter;
    }
}
