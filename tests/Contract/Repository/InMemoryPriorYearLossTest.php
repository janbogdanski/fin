<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;

final class InMemoryPriorYearLossTest extends PriorYearLossCrudContractTestCase
{
    protected function createCrud(): PriorYearLossCrudPort
    {
        return new InMemoryPriorYearLossCrud();
    }
}
