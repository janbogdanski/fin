<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Infrastructure\Doctrine\DoctrineDividendResultQueryAdapter;
use App\TaxCalc\Infrastructure\Doctrine\DoctrineDividendResultRepository;
use App\Tests\Support\SeedsDatabaseUser;

final class DoctrineDividendResultTest extends DividendResultContractTestCase
{
    use SeedsDatabaseUser;

    protected function freshUserId(): UserId
    {
        $userId = UserId::generate();
        $this->seedUser($userId);

        return $userId;
    }

    protected function createRepository(): DividendResultRepositoryPort
    {
        return self::getContainer()->get(DoctrineDividendResultRepository::class);
    }

    protected function createQuery(): DividendResultQueryPort
    {
        return self::getContainer()->get(DoctrineDividendResultQueryAdapter::class);
    }
}
