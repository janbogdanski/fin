<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Infrastructure\Doctrine\DoctrineImportStorageAdapter;

final class DoctrineImportStorageTest extends ImportStorageContractTestCase
{
    protected function createStorage(): ImportStoragePort
    {
        return self::getContainer()->get(DoctrineImportStorageAdapter::class);
    }
}
