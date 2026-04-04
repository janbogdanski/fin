<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Infrastructure\Doctrine\PriorYearLossRepository;

final class DoctrinePriorYearLossCrudTest extends PriorYearLossCrudContractTestCase
{
    protected function createCrud(): PriorYearLossCrudPort
    {
        return self::getContainer()->get(PriorYearLossRepository::class);
    }
}
