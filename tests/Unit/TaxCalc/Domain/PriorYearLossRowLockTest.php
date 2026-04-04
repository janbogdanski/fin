<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Application\Dto\PriorYearLossRow;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PriorYearLossRow lock guard.
 *
 * Verifies that isUsedInAnyYear() correctly reflects the usedInYears state,
 * which gates delete and amount-reduction operations.
 *
 * P0-010: PriorYearLoss mutable after use
 */
final class PriorYearLossRowLockTest extends TestCase
{
    /**
     * @param list<int> $usedInYears
     */
    private function makeRow(array $usedInYears = []): PriorYearLossRow
    {
        return new PriorYearLossRow(
            id: 'test-id',
            lossYear: 2022,
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('5000.00'),
            remainingAmount: BigDecimal::of('5000.00'),
            createdAt: new \DateTimeImmutable('2022-04-30'),
            usedInYears: $usedInYears,
        );
    }

    public function testIsUsedInAnyYearReturnsFalseForUnusedLoss(): void
    {
        $row = $this->makeRow([]);

        self::assertFalse($row->isUsedInAnyYear());
    }

    public function testIsUsedInAnyYearReturnsTrueAfterSingleYearMark(): void
    {
        /** @var list<int> $years */
        $years = [2023];
        $row = $this->makeRow($years);

        self::assertTrue($row->isUsedInAnyYear());
    }

    public function testIsUsedInAnyYearReturnsTrueForMultipleYears(): void
    {
        /** @var list<int> $years */
        $years = [2023, 2024];
        $row = $this->makeRow($years);

        self::assertTrue($row->isUsedInAnyYear());
    }

    public function testUsedInYearsDefaultsToEmptyArray(): void
    {
        $row = new PriorYearLossRow(
            id: 'test-id',
            lossYear: 2022,
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('5000.00'),
            remainingAmount: BigDecimal::of('5000.00'),
            createdAt: new \DateTimeImmutable('2022-04-30'),
        );

        self::assertFalse($row->isUsedInAnyYear());
        self::assertSame([], $row->usedInYears);
    }
}
