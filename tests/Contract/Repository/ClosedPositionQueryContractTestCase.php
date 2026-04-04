<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\Factory\ClosedPositionMother;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Abstract contract test for ClosedPositionQueryPort.
 *
 * Any implementation (InMemory, Doctrine) must satisfy these behavioral
 * contracts. Subclasses provide the concrete SUT via createQuery() and
 * the seeding mechanism via seedPosition().
 */
abstract class ClosedPositionQueryContractTestCase extends KernelTestCase
{
    private ClosedPositionQueryPort $query;

    protected function setUp(): void
    {
        $this->query = $this->createQuery();
    }

    // --- findByUserYearAndCategory() ---

    public function testFindByUserYearAndCategoryReturnsEmptyForNewUser(): void
    {
        $userId = UserId::generate();

        $result = $this->query->findByUserYearAndCategory($userId, TaxYear::of(2025), TaxCategory::EQUITY);

        self::assertSame([], $result);
    }

    public function testFindByUserYearAndCategoryReturnsSeededPositions(): void
    {
        $userId = UserId::generate();
        $position = ClosedPositionMother::standard();

        $this->seedPosition($userId, $position, TaxCategory::EQUITY);

        $result = $this->query->findByUserYearAndCategory($userId, TaxYear::of(2025), TaxCategory::EQUITY);

        self::assertCount(1, $result);
    }

    public function testFindByUserYearAndCategoryFiltersByYear(): void
    {
        $userId = UserId::generate();

        $position2025 = ClosedPositionMother::standard();
        $position2024 = $this->makePositionWithSellYear(2024);

        $this->seedPosition($userId, $position2025, TaxCategory::EQUITY);
        $this->seedPosition($userId, $position2024, TaxCategory::EQUITY);

        $result = $this->query->findByUserYearAndCategory($userId, TaxYear::of(2025), TaxCategory::EQUITY);

        self::assertCount(1, $result);
    }

    public function testFindByUserYearAndCategoryFiltersByCategory(): void
    {
        $userId = UserId::generate();

        $this->seedPosition($userId, ClosedPositionMother::standard(), TaxCategory::EQUITY);
        $this->seedPosition($userId, ClosedPositionMother::withGain('200.00'), TaxCategory::DERIVATIVE);

        $result = $this->query->findByUserYearAndCategory($userId, TaxYear::of(2025), TaxCategory::EQUITY);

        self::assertCount(1, $result);
    }

    public function testFindByUserYearAndCategoryIsolatedPerUser(): void
    {
        $user1 = UserId::generate();
        $user2 = UserId::generate();

        $this->seedPosition($user1, ClosedPositionMother::standard(), TaxCategory::EQUITY);
        $this->seedPosition($user2, ClosedPositionMother::withGain('200.00'), TaxCategory::EQUITY);

        $result1 = $this->query->findByUserYearAndCategory($user1, TaxYear::of(2025), TaxCategory::EQUITY);
        $result2 = $this->query->findByUserYearAndCategory($user2, TaxYear::of(2025), TaxCategory::EQUITY);

        self::assertCount(1, $result1);
        self::assertCount(1, $result2);
    }

    // --- countByUserAndYear() ---

    public function testCountByUserAndYearReturnsZeroForNewUser(): void
    {
        $userId = UserId::generate();

        self::assertSame(0, $this->query->countByUserAndYear($userId, TaxYear::of(2025)));
    }

    public function testCountByUserAndYearCountsAcrossCategories(): void
    {
        $userId = UserId::generate();

        $this->seedPosition($userId, ClosedPositionMother::standard(), TaxCategory::EQUITY);
        $this->seedPosition($userId, ClosedPositionMother::withGain('200.00'), TaxCategory::DERIVATIVE);

        self::assertSame(2, $this->query->countByUserAndYear($userId, TaxYear::of(2025)));
    }

    public function testCountByUserAndYearFiltersByYear(): void
    {
        $userId = UserId::generate();

        $this->seedPosition($userId, ClosedPositionMother::standard(), TaxCategory::EQUITY);
        $this->seedPosition($userId, $this->makePositionWithSellYear(2024), TaxCategory::EQUITY);

        self::assertSame(1, $this->query->countByUserAndYear($userId, TaxYear::of(2025)));
    }

    abstract protected function createQuery(): ClosedPositionQueryPort;

    abstract protected function seedPosition(UserId $userId, ClosedPosition $position, TaxCategory $category): void;

    private function makePositionWithSellYear(int $year): ClosedPosition
    {
        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('6075.00'),
            proceedsPLN: BigDecimal::of('6885.00'),
            buyCommissionPLN: BigDecimal::of('4.05'),
            sellCommissionPLN: BigDecimal::of('4.05'),
            gainLossPLN: BigDecimal::of('801.90'),
            buyDate: new \DateTimeImmutable(sprintf('%d-03-10', $year)),
            sellDate: new \DateTimeImmutable(sprintf('%d-06-15', $year)),
            buyNBPRate: \App\Tests\Factory\NBPRateMother::usd405(new \DateTimeImmutable(sprintf('%d-03-07', $year))),
            sellNBPRate: \App\Tests\Factory\NBPRateMother::usd405(new \DateTimeImmutable(sprintf('%d-06-13', $year))),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
