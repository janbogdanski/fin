<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use App\Tests\InMemory\InMemoryPriorYearLossQueryAdapter;

final class InMemoryPriorYearLossQueryTest extends PriorYearLossQueryContractTestCase
{
    private InMemoryPriorYearLossCrud $crud;

    protected function setUp(): void
    {
        $this->crud = new InMemoryPriorYearLossCrud();
        parent::setUp();
    }

    protected function createCrud(): PriorYearLossCrudPort
    {
        return $this->crud;
    }

    protected function createQuery(): PriorYearLossQueryPort
    {
        return new InMemoryPriorYearLossQueryAdapter($this->crud);
    }
}
