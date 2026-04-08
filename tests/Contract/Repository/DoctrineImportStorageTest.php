<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Infrastructure\Doctrine\DoctrineImportStorageAdapter;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Support\SeedsDatabaseUser;

final class DoctrineImportStorageTest extends ImportStorageContractTestCase
{
    use SeedsDatabaseUser;

    protected function freshUserId(): UserId
    {
        $userId = UserId::generate();
        $this->seedUser($userId);

        return $userId;
    }

    protected function createStorage(): ImportStoragePort
    {
        $storage = self::getContainer()->get(DoctrineImportStorageAdapter::class);
        self::assertInstanceOf(DoctrineImportStorageAdapter::class, $storage);

        return $storage;
    }
}
