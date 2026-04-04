<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Abstract contract test for PriorYearLossCrudPort.
 *
 * Any implementation (InMemory, Doctrine) must satisfy these behavioral
 * contracts. Subclasses provide the concrete SUT via createCrud().
 */
abstract class PriorYearLossCrudContractTestCase extends KernelTestCase
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

        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertCount(1, $rows);
        self::assertSame(2022, $rows[0]->lossYear);
        self::assertSame(TaxCategory::EQUITY, $rows[0]->taxCategory);
        self::assertTrue($rows[0]->originalAmount->isEqualTo(BigDecimal::of('5000.00')));
        self::assertTrue($rows[0]->remainingAmount->isEqualTo(BigDecimal::of('5000.00')));
        self::assertNotEmpty($rows[0]->id);
    }

    public function testSaveMultipleLossesForSameUser(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2021, TaxCategory::EQUITY, BigDecimal::of('3000.00'));
        $this->crud->save($userId, 2022, TaxCategory::DERIVATIVE, BigDecimal::of('2000.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    public function testFindByUserOrdersByYearAscending(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2023, TaxCategory::EQUITY, BigDecimal::of('1000.00'));
        $this->crud->save($userId, 2021, TaxCategory::EQUITY, BigDecimal::of('3000.00'));
        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('2000.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertSame(2021, $rows[0]->lossYear);
        self::assertSame(2022, $rows[1]->lossYear);
        self::assertSame(2023, $rows[2]->lossYear);
    }

    // --- upsert behavior ---

    public function testSaveUpsertsOnSameYearAndCategory(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('7500.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertCount(1, $rows);
        self::assertTrue($rows[0]->originalAmount->isEqualTo(BigDecimal::of('7500.00')));
        self::assertTrue($rows[0]->remainingAmount->isEqualTo(BigDecimal::of('7500.00')));
    }

    public function testSaveDoesNotUpsertDifferentCategories(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $this->crud->save($userId, 2022, TaxCategory::DERIVATIVE, BigDecimal::of('3000.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    public function testSaveDoesNotUpsertDifferentYears(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2021, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('3000.00'));

        $rows = $this->crud->findByUser($userId);

        self::assertCount(2, $rows);
    }

    // --- delete() ---

    public function testDeleteRemovesExistingLoss(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $rows = $this->crud->findByUser($userId);
        $id = $rows[0]->id;

        $this->crud->delete($id, $userId);

        self::assertSame([], $this->crud->findByUser($userId));
    }

    public function testDeleteDoesNothingForNonExistentId(): void
    {
        $userId = UserId::generate();

        $this->crud->save($userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));

        $this->crud->delete('00000000-0000-0000-0000-000000000000', $userId);

        self::assertCount(1, $this->crud->findByUser($userId));
    }

    public function testDeleteRequiresMatchingUserId(): void
    {
        $owner = UserId::generate();
        $attacker = UserId::generate();

        $this->crud->save($owner, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $rows = $this->crud->findByUser($owner);
        $id = $rows[0]->id;

        $this->crud->delete($id, $attacker);

        self::assertCount(1, $this->crud->findByUser($owner));
    }

    // --- user isolation ---

    public function testFindByUserIsolatedPerUser(): void
    {
        $user1 = UserId::generate();
        $user2 = UserId::generate();

        $this->crud->save($user1, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00'));
        $this->crud->save($user2, 2022, TaxCategory::EQUITY, BigDecimal::of('3000.00'));

        $rows1 = $this->crud->findByUser($user1);
        $rows2 = $this->crud->findByUser($user2);

        self::assertCount(1, $rows1);
        self::assertCount(1, $rows2);
        self::assertTrue($rows1[0]->originalAmount->isEqualTo(BigDecimal::of('5000.00')));
        self::assertTrue($rows2[0]->originalAmount->isEqualTo(BigDecimal::of('3000.00')));
    }

    abstract protected function createCrud(): PriorYearLossCrudPort;
}
