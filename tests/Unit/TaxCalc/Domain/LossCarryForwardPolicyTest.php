<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Policy\LossCarryForwardPolicy;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class LossCarryForwardPolicyTest extends TestCase
{
    /**
     * Strata 10 000 PLN z 2023, rozliczenie w 2024.
     * Max 50% z 10 000 = 5 000 PLN.
     */
    public function testCalculatesCorrectMaxDeduction(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('10000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('5000'));
        self::assertSame(TaxCategory::EQUITY, $range->taxCategory);
        self::assertSame(4, $range->yearsRemaining);
    }

    /**
     * Strata z 2019, rozliczenie w 2025 — wygasla (> 5 lat).
     */
    public function testReturnsNullForExpiredLoss(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2019),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('5000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2025));

        self::assertNull($range);
    }

    /**
     * Strata z 2020, rozliczenie w 2025 — ostatni rok (5-ty).
     * yearsRemaining = 0, ale jeszcze mozna odliczyc.
     */
    public function testFifthYearIsStillValid(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2020),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('3000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2025));

        self::assertNotNull($range);
        self::assertSame(0, $range->yearsRemaining);
        // max 50% of 10000 = 5000, ale remaining to tylko 3000
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('3000'));
    }

    /**
     * Max 50% z original, nawet jesli remaining > 50%.
     * Original: 10 000, remaining: 8 000 -> max = 5 000 (50% of original).
     */
    public function testMaxFiftyPercentOfOriginal(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('8000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('5000'));
    }

    /**
     * Remaining < 50% original -> max = remaining.
     */
    public function testRemainingLessThanFiftyPercentUsesRemaining(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('2000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('2000'));
    }

    /**
     * Zero remaining -> null (nic do odliczenia).
     */
    public function testReturnsNullForZeroRemaining(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::zero(),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNull($range);
    }

    /**
     * Krypto straty osobno od equity.
     */
    public function testCryptoLossHasSeparateCategory(): void
    {
        $cryptoLoss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::CRYPTO,
            originalAmount: BigDecimal::of('5000'),
            remainingAmount: BigDecimal::of('5000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($cryptoLoss, TaxYear::of(2024));

        self::assertNotNull($range);
        self::assertSame(TaxCategory::CRYPTO, $range->taxCategory);
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('2500'));
    }

    /**
     * Rok biezacy <= rok straty -> blad.
     */
    public function testRejectsCurrentYearNotAfterLossYear(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2024),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('10000'),
        );

        $this->expectException(\InvalidArgumentException::class);

        LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));
    }

    /**
     * Expires in year jest poprawnie obliczany.
     */
    public function testExpiresInYearIsCorrect(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2022),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10000'),
            remainingAmount: BigDecimal::of('10000'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        // 2022 + 5 = 2027
        self::assertTrue($range->expiresInYear->equals(TaxYear::of(2027)));
        self::assertSame(3, $range->yearsRemaining);
    }

    /**
     * Nieparzysta kwota originala — zaokraglenie 50% w dol do groszy.
     */
    public function testOddOriginalAmountRoundsDownMaxDeduction(): void
    {
        $loss = new PriorYearLoss(
            taxYear: TaxYear::of(2023),
            taxCategory: TaxCategory::EQUITY,
            originalAmount: BigDecimal::of('10001'),
            remainingAmount: BigDecimal::of('10001'),
        );

        $range = LossCarryForwardPolicy::calculateRange($loss, TaxYear::of(2024));

        self::assertNotNull($range);
        // 50% of 10001 = 5000.50 -> rounded DOWN = 5000.50 (scale 2)
        self::assertTrue($range->maxDeductionThisYear->isEqualTo('5000.50'));
    }
}
