<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\BrokerImport\Application\Port\ImportStoragePort;
use App\Tests\InMemory\InMemoryImportStorageAdapter;

final class InMemoryImportStorageTest extends ImportStorageContractTestCase
{
    protected function createStorage(): ImportStoragePort
    {
        return new InMemoryImportStorageAdapter();
    }
}
