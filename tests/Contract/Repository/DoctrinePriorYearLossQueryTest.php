<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Infrastructure\Doctrine\DoctrinePriorYearLossQueryAdapter;
use App\TaxCalc\Infrastructure\Doctrine\PriorYearLossRepository;

final class DoctrinePriorYearLossQueryTest extends PriorYearLossQueryContractTestCase
{
    protected function createCrud(): PriorYearLossCrudPort
    {
        return self::getContainer()->get(PriorYearLossRepository::class);
    }

    protected function createQuery(): PriorYearLossQueryPort
    {
        return self::getContainer()->get(DoctrinePriorYearLossQueryAdapter::class);
    }
}
