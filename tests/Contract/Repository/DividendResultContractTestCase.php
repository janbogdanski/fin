<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\Factory\NBPRateMother;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Abstract contract test for DividendResultRepositoryPort + DividendResultQueryPort.
 *
 * These two ports form a write/read pair. Both implementations (InMemory, Doctrine)
 * must satisfy these behavioral contracts. Subclasses provide concrete SUTs.
 */
abstract class DividendResultContractTestCase extends KernelTestCase
{
    private DividendResultRepositoryPort $repository;

    private DividendResultQueryPort $query;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
        $this->query = $this->createQuery();
    }

    // --- findByUserAndYear() ---

    public function testFindByUserAndYearReturnsEmptyForNewUser(): void
    {
        $userId = UserId::generate();

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testSaveAllPersistsResults(): void
    {
        $userId = $this->freshUserId();

        $this->repository->saveAll($userId, TaxYear::of(2025), [$this->makeResult()]);

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(1, $result);
    }

    public function testSaveAllMultipleResults(): void
    {
        $userId = $this->freshUserId();

        $this->repository->saveAll($userId, TaxYear::of(2025), [
            $this->makeResult(),
            $this->makeResult(),
        ]);

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(2, $result);
    }

    public function testDeleteByUserAndYearRemovesResults(): void
    {
        $userId = $this->freshUserId();

        $this->repository->saveAll($userId, TaxYear::of(2025), [
            $this->makeResult(),
            $this->makeResult(),
        ]);

        $this->repository->deleteByUserAndYear($userId, TaxYear::of(2025));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testDeleteByUserAndYearIsIdempotent(): void
    {
        $userId = $this->freshUserId();

        // Should not throw on empty user/year
        $this->repository->deleteByUserAndYear($userId, TaxYear::of(2025));

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testSaveAllIsIdempotentViaDeleteBeforeSave(): void
    {
        $userId = $this->freshUserId();

        $this->repository->saveAll($userId, TaxYear::of(2025), [
            $this->makeResult(),
            $this->makeResult(),
        ]);
        $this->repository->deleteByUserAndYear($userId, TaxYear::of(2025));
        $this->repository->saveAll($userId, TaxYear::of(2025), [$this->makeResult()]);

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2025));

        self::assertCount(1, $result);
    }

    public function testResultsIsolatedPerUser(): void
    {
        $user1 = $this->freshUserId();
        $user2 = UserId::generate();

        $this->repository->saveAll($user1, TaxYear::of(2025), [$this->makeResult()]);

        $result = $this->query->findByUserAndYear($user2, TaxYear::of(2025));

        self::assertSame([], $result);
    }

    public function testResultsIsolatedPerYear(): void
    {
        $userId = $this->freshUserId();

        $this->repository->saveAll($userId, TaxYear::of(2025), [$this->makeResult()]);

        $result = $this->query->findByUserAndYear($userId, TaxYear::of(2024));

        self::assertSame([], $result);
    }

    protected function freshUserId(): UserId
    {
        return UserId::generate();
    }

    abstract protected function createRepository(): DividendResultRepositoryPort;

    abstract protected function createQuery(): DividendResultQueryPort;

    private function makeResult(): DividendTaxResult
    {
        return new DividendTaxResult(
            grossDividendPLN: Money::of('100.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('15.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.150000'),
            upoRate: BigDecimal::of('0.150000'),
            polishTaxDue: Money::of('4.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: NBPRateMother::usd405(new \DateTimeImmutable('2025-01-14')),
        );
    }
}
