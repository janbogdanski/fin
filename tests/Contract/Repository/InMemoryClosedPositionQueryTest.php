<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\Tests\InMemory\InMemoryClosedPositionQueryAdapter;

final class InMemoryClosedPositionQueryTest extends ClosedPositionQueryContractTestCase
{
    private InMemoryClosedPositionQueryAdapter $adapter;

    protected function createQuery(): ClosedPositionQueryPort
    {
        $this->adapter = new InMemoryClosedPositionQueryAdapter();

        return $this->adapter;
    }

    protected function seedPosition(UserId $userId, ClosedPosition $position, TaxCategory $category): void
    {
        $this->adapter->seed($userId, $position, $category);
    }
}
