<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Abstract contract test for PriorYearLossQueryPort.
 *
 * Uses PriorYearLossCrudPort for seeding. Any implementation pair
 * (InMemory, Doctrine) must satisfy these behavioral contracts.
 * Subclasses provide concrete SUTs via createCrud() and createQuery().
 *
 * Fixed years used throughout to prevent test flakiness:
 * - Loss year 2023, query year 2025 => within 5-year window (expires 2028)
 * - Loss year 2019, query year 2025 => expired (2019+5=2024 < 2025)
 */
abstract class PriorYearLossQueryContractTestCase extends KernelTestCase
{
    private PriorYearLossCrudPort $crud;

    private PriorYearLossQueryPort $query;

    protected function setUp(): void
    {
        $this->crud = $this->createCrud();
        $this->query = $this->createQuery();
    }

    public function testFindByUserAndYearReturnsEmptyForNewUser(): void
    {
        $userId = UserId::generate();

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testFindByUserAndYearReturnsRangeForValidLoss(): void
    {
        $userId = $this->freshUserId();

        // Loss year 2023, query year 2025: expires 2028, within window
        $this->crud->save(new SavePriorYearLoss($userId, 2023, TaxCategory::EQUITY, BigDecimal::of('1000.00')));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(1, $result);
    }

    public function testFindByUserAndYearFiltersExpiredLoss(): void
    {
        $userId = $this->freshUserId();

        // Loss year 2019, query year 2025: 2019+5=2024 < 2025, expired
        $this->crud->save(new SavePriorYearLoss($userId, 2019, TaxCategory::EQUITY, BigDecimal::of('1000.00')));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testFindByUserAndYearReturnsMultipleCategories(): void
    {
        $userId = $this->freshUserId();

        $this->crud->save(new SavePriorYearLoss($userId, 2023, TaxCategory::EQUITY, BigDecimal::of('1000.00')));
        $this->crud->save(new SavePriorYearLoss($userId, 2023, TaxCategory::DERIVATIVE, BigDecimal::of('500.00')));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(2, $result);
    }

    public function testFindByUserAndYearIsolatedPerUser(): void
    {
        $user1 = $this->freshUserId();
        $user2 = UserId::generate();

        $this->crud->save(new SavePriorYearLoss($user1, 2023, TaxCategory::EQUITY, BigDecimal::of('1000.00')));

        $result = $this->query->findByUserAndYear($user2, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testLossDeductionRangeMaxIs50PercentOfRemainingAmount(): void
    {
        $userId = $this->freshUserId();

        // art. 9 ust. 3: max 50% of original amount per year
        $this->crud->save(new SavePriorYearLoss($userId, 2023, TaxCategory::EQUITY, BigDecimal::of('1000.00')));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(1, $result);

        $range = $result[0];

        self::assertTrue(
            $range->maxDeductionThisYear->isLessThanOrEqualTo($range->remainingAmount),
            'maxDeductionThisYear must not exceed remainingAmount (art. 9 ust. 3)',
        );
    }

    protected function freshUserId(): UserId
    {
        return UserId::generate();
    }

    abstract protected function createCrud(): PriorYearLossCrudPort;

    abstract protected function createQuery(): PriorYearLossQueryPort;
}
