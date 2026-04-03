<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use PHPUnit\Framework\TestCase;

/**
 * Abstract contract test for PriorYearLossCrudPort.
 *
 * Any implementation (InMemory, Doctrine) must satisfy these behavioral
 * contracts. Subclasses provide the concrete SUT via createCrud().
 */
abstract class PriorYearLossCrudContractTestCase extends TestCase
{
    private PriorYearLossCrudPort $crud;

    protected function setUp(): void
    {
        $this->crud = $this->createCrud();
    }

    // --- findByUser() ---

    public function testFindByUserReturnsEmptyForNewUser(): void
    {
        $result = $this->crud->findByUser(UserId::generate());

        self::assertSame([], $result);
    }

    // --- save() ---

    public function testSaveCreatesPriorYearLoss(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, 'capital_gains', '5000.00');

        $rows = $this->crud->findByUser($userId);

        self::assertCount(1, $rows);
        self::assertSame(2022, $rows[0]['loss_year']);
        self::assertSame('capital_gains', $rows[0]['tax_category']);
        self::assertSame('5000.00', $rows[0]['original_amount']);
        self::assertSame('5000.00', $rows[0]['remaining_amount']);
        self::assertNotEmpty($rows[0]['id']);
        self::assertNotEmpty($rows[0]['created_at']);
    }

    public function testSaveMultipleLossesForSameUser(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2021, 'capital_gains', '3000.00');
        $this->crud->save($userId, 2022, 'dividends', '2000.00');

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    public function testFindByUserOrdersByYearAscending(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2023, 'capital_gains', '1000.00');
        $this->crud->save($userId, 2021, 'capital_gains', '3000.00');
        $this->crud->save($userId, 2022, 'capital_gains', '2000.00');

        $rows = $this->crud->findByUser($userId);

        self::assertSame(2021, $rows[0]['loss_year']);
        self::assertSame(2022, $rows[1]['loss_year']);
        self::assertSame(2023, $rows[2]['loss_year']);
    }

    // --- upsert behavior ---

    public function testSaveUpsertsOnSameYearAndCategory(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, 'capital_gains', '5000.00');
        $this->crud->save($userId, 2022, 'capital_gains', '7500.00');

        $rows = $this->crud->findByUser($userId);

        self::assertCount(1, $rows);
        self::assertSame('7500.00', $rows[0]['original_amount']);
        self::assertSame('7500.00', $rows[0]['remaining_amount']);
    }

    public function testSaveDoesNotUpsertDifferentCategories(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, 'capital_gains', '5000.00');
        $this->crud->save($userId, 2022, 'dividends', '3000.00');

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    public function testSaveDoesNotUpsertDifferentYears(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2021, 'capital_gains', '5000.00');
        $this->crud->save($userId, 2022, 'capital_gains', '3000.00');

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    // --- delete() ---

    public function testDeleteRemovesExistingLoss(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, 'capital_gains', '5000.00');
        $rows = $this->crud->findByUser($userId);
        $id = $rows[0]['id'];

        $this->crud->delete($id, $userId);

        self::assertSame([], $this->crud->findByUser($userId));
    }

    public function testDeleteDoesNothingForNonExistentId(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, 'capital_gains', '5000.00');

        $this->crud->delete('non-existent-id', $userId);

        self::assertCount(1, $this->crud->findByUser($userId));
    }

    public function testDeleteRequiresMatchingUserId(): void
    {
        $owner = UserId::generate();
        $attacker = UserId::generate();

        $this->crud->save($owner, 2022, 'capital_gains', '5000.00');
        $rows = $this->crud->findByUser($owner);
        $id = $rows[0]['id'];

        // Attacker tries to delete owner's loss
        $this->crud->delete($id, $attacker);

        // Owner's data should still be there
        self::assertCount(1, $this->crud->findByUser($owner));
    }

    // --- user isolation ---

    public function testFindByUserIsolatedPerUser(): void
    {
        $user1 = UserId::generate();
        $user2 = UserId::generate();

        $this->crud->save($user1, 2022, 'capital_gains', '5000.00');
        $this->crud->save($user2, 2022, 'capital_gains', '3000.00');

        $rows1 = $this->crud->findByUser($user1);
        $rows2 = $this->crud->findByUser($user2);

        self::assertCount(1, $rows1);
        self::assertCount(1, $rows2);
        self::assertSame('5000.00', $rows1[0]['original_amount']);
        self::assertSame('3000.00', $rows2[0]['original_amount']);
    }

    abstract protected function createCrud(): PriorYearLossCrudPort;
}
