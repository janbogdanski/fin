<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class LossDeductionRangeTest extends TestCase
{
    public function testCreatesValidRange(): void
    {
        $range = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('4000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        self::assertTrue($range->originalAmount->isEqualTo('6000.00'));
        self::assertTrue($range->remainingAmount->isEqualTo('4000.00'));
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('3000.00'));
    }

    public function testRejectsNegativeOriginalAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('originalAmount');

        new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('-1.00'),
            remainingAmount: BigDecimal::of('0'),
            maxDeductionThisYear: BigDecimal::of('0'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );
    }

    public function testRejectsNegativeRemainingAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('remainingAmount');

        new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('-1.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );
    }

    public function testRejectsRemainingGreaterThanOriginal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('remainingAmount must not exceed originalAmount');

        new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('3000.00'),
            remainingAmount: BigDecimal::of('5000.00'),
            maxDeductionThisYear: BigDecimal::of('1500.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );
    }

    public function testRejectsNegativeMaxDeduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDeductionThisYear');

        new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('-1.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );
    }

    public function testAcceptsZeroAmounts(): void
    {
        $range = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::zero(),
            remainingAmount: BigDecimal::zero(),
            maxDeductionThisYear: BigDecimal::zero(),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        self::assertTrue($range->originalAmount->isZero());
        self::assertTrue($range->remainingAmount->isZero());
        self::assertTrue($range->maxDeductionThisYear->isZero());
    }

    public function testAcceptsRemainingEqualToOriginal(): void
    {
        $range = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        self::assertTrue($range->remainingAmount->isEqualTo($range->originalAmount));
    }
}
