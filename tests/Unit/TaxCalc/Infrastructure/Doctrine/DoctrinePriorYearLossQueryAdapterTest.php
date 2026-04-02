<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\TaxCalc\Infrastructure\Doctrine\DoctrinePriorYearLossQueryAdapter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DoctrinePriorYearLossQueryAdapterTest extends TestCase
{
    /**
     * AC1: Adapter returns real data from DB (not []).
     */
    public function testReturnsLossDeductionRangesFromDbRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2023,
                    'tax_category' => 'EQUITY',
                    'original_amount' => '10000.00',
                    'remaining_amount' => '10000.00',
                ],
            ]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertCount(1, $ranges);
        self::assertSame(TaxCategory::EQUITY, $ranges[0]->taxCategory);
        self::assertTrue($ranges[0]->originalAmount->isEqualTo('10000.00'));
        self::assertTrue($ranges[0]->remainingAmount->isEqualTo('10000.00'));
        // 50% cap: max deduction = 5000
        self::assertTrue($ranges[0]->maxDeductionThisYear->isEqualTo('5000.00'));
        self::assertSame(4, $ranges[0]->yearsRemaining);
    }

    /**
     * AC4: Empty losses = no error, deduction = 0.
     */
    public function testReturnsEmptyArrayWhenNoLosses(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertSame([], $ranges);
    }

    /**
     * AC5: Loss older than 5 years is excluded (expired).
     */
    public function testExcludesExpiredLosses(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2018,
                    'tax_category' => 'EQUITY',
                    'original_amount' => '10000.00',
                    'remaining_amount' => '5000.00',
                ],
            ]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertSame([], $ranges);
    }

    /**
     * AC3: Crypto losses use separate category.
     */
    public function testReturnsCryptoLossesWithCorrectCategory(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2023,
                    'tax_category' => 'CRYPTO',
                    'original_amount' => '8000.00',
                    'remaining_amount' => '8000.00',
                ],
            ]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertCount(1, $ranges);
        self::assertSame(TaxCategory::CRYPTO, $ranges[0]->taxCategory);
        // 50% of 8000 = 4000
        self::assertTrue($ranges[0]->maxDeductionThisYear->isEqualTo('4000.00'));
    }

    /**
     * Zero remaining losses are excluded (nothing to deduct).
     */
    public function testExcludesFullyUsedLosses(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2023,
                    'tax_category' => 'EQUITY',
                    'original_amount' => '10000.00',
                    'remaining_amount' => '0.00',
                ],
            ]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertSame([], $ranges);
    }

    /**
     * Multiple losses: valid ones kept, expired/fully-used filtered out.
     */
    public function testMixedLossesFilteredCorrectly(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                // Valid: 2022 loss for 2024 tax year
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2022,
                    'tax_category' => 'EQUITY',
                    'original_amount' => '6000.00',
                    'remaining_amount' => '3000.00',
                ],
                // Expired: 2017 loss for 2024 tax year (> 5 years)
                [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2017,
                    'tax_category' => 'EQUITY',
                    'original_amount' => '5000.00',
                    'remaining_amount' => '2000.00',
                ],
                // Valid: 2023 crypto loss
                [
                    'id' => '00000000-0000-0000-0000-000000000003',
                    'user_id' => '11111111-1111-1111-1111-111111111111',
                    'loss_year' => 2023,
                    'tax_category' => 'CRYPTO',
                    'original_amount' => '4000.00',
                    'remaining_amount' => '4000.00',
                ],
            ]);

        $adapter = new DoctrinePriorYearLossQueryAdapter($connection);

        $ranges = $adapter->findByUserAndYear(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            TaxYear::of(2024),
        );

        self::assertCount(2, $ranges);
        self::assertSame(TaxCategory::EQUITY, $ranges[0]->taxCategory);
        self::assertSame(TaxCategory::CRYPTO, $ranges[1]->taxCategory);
    }
}
