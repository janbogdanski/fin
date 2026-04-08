<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Infrastructure\Doctrine\PriorYearLossRepository;
use App\Tests\Support\SeedsDatabaseUser;

final class DoctrinePriorYearLossCrudTest extends PriorYearLossCrudContractTestCase
{
    use SeedsDatabaseUser;

    protected function freshUserId(): UserId
    {
        $userId = UserId::generate();
        $this->seedUser($userId);

        return $userId;
    }

    protected function createCrud(): PriorYearLossCrudPort
    {
        $crud = self::getContainer()->get(PriorYearLossRepository::class);
        self::assertInstanceOf(PriorYearLossRepository::class, $crud);

        return $crud;
    }
}
