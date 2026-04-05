<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for lock guard in InMemoryPriorYearLossCrud.
 *
 * Verifies that:
 * - markUsedInYear() persists the year
 * - delete() throws DomainException when loss is used
 * - save() throws DomainException when reducing originalAmount on a used loss
 * - save() allows increasing or equal amount on a used loss
 *
 * P0-010: PriorYearLoss mutable after use
 */
final class InMemoryPriorYearLossLockGuardTest extends TestCase
{
    private UserId $userId;

    private InMemoryPriorYearLossCrud $crud;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->crud = new InMemoryPriorYearLossCrud();
        $this->crud->save(new SavePriorYearLoss($this->userId, 2022, TaxCategory::EQUITY, BigDecimal::of('10000.00')));
    }

    public function testMarkUsedInYearSetsUsedYearOnRow(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        $rows = $this->crud->findByUser($this->userId);

        self::assertCount(1, $rows);
        self::assertContains(2023, $rows[0]->usedInYears);
    }

    public function testMarkUsedInYearCanBeCalledTwiceForDifferentYears(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2024);

        $rows = $this->crud->findByUser($this->userId);

        self::assertCount(1, $rows);
        self::assertContains(2023, $rows[0]->usedInYears);
        self::assertContains(2024, $rows[0]->usedInYears);
    }

    public function testMarkUsedInYearIsIdempotentForSameYear(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        $rows = $this->crud->findByUser($this->userId);

        self::assertCount(1, $rows);
        self::assertCount(1, $rows[0]->usedInYears);
    }

    public function testDeleteThrowsDomainExceptionWhenLossIsUsed(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        $rows = $this->crud->findByUser($this->userId);
        $id = $rows[0]->id;

        $this->expectException(\DomainException::class);

        $this->crud->delete($id, $this->userId);
    }

    public function testDeleteSucceedsWhenLossIsNotUsed(): void
    {
        $rows = $this->crud->findByUser($this->userId);
        $id = $rows[0]->id;

        $this->crud->delete($id, $this->userId);

        self::assertSame([], $this->crud->findByUser($this->userId));
    }

    public function testSaveThrowsDomainExceptionWhenReducingAmountOnUsedLoss(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        $this->expectException(\DomainException::class);

        // Attempt to reduce original amount from 10000 to 5000 on a used loss
        $this->crud->save(new SavePriorYearLoss($this->userId, 2022, TaxCategory::EQUITY, BigDecimal::of('5000.00')));
    }

    public function testSaveAllowsEqualAmountOnUsedLoss(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        // Should not throw — same amount is safe
        $this->crud->save(new SavePriorYearLoss($this->userId, 2022, TaxCategory::EQUITY, BigDecimal::of('10000.00')));

        $rows = $this->crud->findByUser($this->userId);
        self::assertTrue($rows[0]->originalAmount->isEqualTo(BigDecimal::of('10000.00')));
    }

    public function testSaveAllowsIncreasedAmountOnUsedLoss(): void
    {
        $this->crud->markUsedInYear($this->userId, 2022, TaxCategory::EQUITY, 2023);

        // Should not throw — increasing amount is safe (user corrects upward)
        $this->crud->save(new SavePriorYearLoss($this->userId, 2022, TaxCategory::EQUITY, BigDecimal::of('12000.00')));

        $rows = $this->crud->findByUser($this->userId);
        self::assertTrue($rows[0]->originalAmount->isEqualTo(BigDecimal::of('12000.00')));
    }
}
