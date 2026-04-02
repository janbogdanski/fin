<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Exception\TaxReconciliationException;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class AnnualTaxCalculationTest extends TestCase
{
    private UserId $userId;

    private TaxYear $taxYear;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->taxYear = TaxYear::of(2025);
    }

    public function testCreatesEmpty(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        self::assertTrue($calc->userId()->equals($this->userId));
        self::assertTrue($calc->taxYear()->equals($this->taxYear));
        self::assertTrue($calc->equityProceeds()->isZero());
        self::assertTrue($calc->equityCostBasis()->isZero());
        self::assertTrue($calc->equityCommissions()->isZero());
        self::assertTrue($calc->equityGainLoss()->isZero());
        self::assertTrue($calc->equityLossDeduction()->isZero());
        self::assertTrue($calc->cryptoProceeds()->isZero());
        self::assertTrue($calc->dividendTotalTaxDue()->isZero());
        self::assertEmpty($calc->dividendsByCountry());
        self::assertFalse($calc->isFinalized());
    }

    /**
     * Equity closed positions: sumuje proceeds, costs, commissions, gain/loss.
     *
     * Position 1: proceeds=10000, cost=8000, buyComm=10, sellComm=5, gain=1985
     * Position 2: proceeds=5000, cost=4500, buyComm=8, sellComm=4, gain=488
     * Totals: proceeds=15000, cost=12500, comm=27, gain=2473
     */
    public function testAddEquityClosedPositions(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $positions = [
            $this->closedPosition(
                proceeds: '10000.00',
                costBasis: '8000.00',
                buyComm: '10.00',
                sellComm: '5.00',
                gainLoss: '1985.00',
            ),
            $this->closedPosition(
                proceeds: '5000.00',
                costBasis: '4500.00',
                buyComm: '8.00',
                sellComm: '4.00',
                gainLoss: '488.00',
            ),
        ];

        $calc->addClosedPositions($positions, TaxCategory::EQUITY);

        self::assertTrue($calc->equityProceeds()->isEqualTo('15000.00'));
        self::assertTrue($calc->equityCostBasis()->isEqualTo('12500.00'));
        self::assertTrue($calc->equityCommissions()->isEqualTo('27.00'));
        self::assertTrue($calc->equityGainLoss()->isEqualTo('2473.00'));

        // Crypto untouched
        self::assertTrue($calc->cryptoProceeds()->isZero());
    }

    /**
     * DERIVATIVE positions go to the same bucket as EQUITY (sekcja C PIT-38).
     */
    public function testDerivativesMergeWithEquity(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '5000.00', costBasis: '3000.00', buyComm: '5.00', sellComm: '5.00', gainLoss: '1990.00')],
            TaxCategory::EQUITY,
        );
        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '2000.00', costBasis: '1500.00', buyComm: '2.00', sellComm: '2.00', gainLoss: '496.00')],
            TaxCategory::DERIVATIVE,
        );

        self::assertTrue($calc->equityProceeds()->isEqualTo('7000.00'));
        self::assertTrue($calc->equityGainLoss()->isEqualTo('2486.00'));
    }

    /**
     * Crypto is a separate basket — does not mix with equity.
     */
    public function testAddCryptoSeparateFromEquity(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $equityPosition = $this->closedPosition(
            proceeds: '10000.00',
            costBasis: '8000.00',
            buyComm: '10.00',
            sellComm: '5.00',
            gainLoss: '1985.00',
        );
        $cryptoPosition = $this->closedPosition(
            proceeds: '20000.00',
            costBasis: '15000.00',
            buyComm: '0.00',
            sellComm: '0.00',
            gainLoss: '5000.00',
        );

        $calc->addClosedPositions([$equityPosition], TaxCategory::EQUITY);
        $calc->addClosedPositions([$cryptoPosition], TaxCategory::CRYPTO);

        // Equity bucket
        self::assertTrue($calc->equityProceeds()->isEqualTo('10000.00'));
        self::assertTrue($calc->equityGainLoss()->isEqualTo('1985.00'));

        // Crypto bucket — separate
        self::assertTrue($calc->cryptoProceeds()->isEqualTo('20000.00'));
        self::assertTrue($calc->cryptoCostBasis()->isEqualTo('15000.00'));
        self::assertTrue($calc->cryptoGainLoss()->isEqualTo('5000.00'));
    }

    /**
     * Dividends aggregated per country.
     * US: 2 dividends, DE: 1 dividend.
     */
    public function testAddDividendPerCountry(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $nbpRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-06-01'), '110/A/NBP/2025');

        // US dividend 1: gross 100 PLN, WHT 15 PLN, polish tax due 4 PLN
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('100.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('15.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('4.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        // US dividend 2: gross 200 PLN, WHT 30 PLN, polish tax due 8 PLN
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('200.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('30.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('8.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $deRate = NBPRate::create(CurrencyCode::EUR, BigDecimal::of('4.30'), new \DateTimeImmutable('2025-07-01'), '130/A/NBP/2025');

        // DE dividend: gross 50 PLN, WHT 13.25 PLN (26.5%), polish tax due 0
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('50.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('13.25', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.265'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('0.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::DE,
            nbpRate: $deRate,
        ));

        $byCountry = $calc->dividendsByCountry();

        self::assertCount(2, $byCountry);
        self::assertArrayHasKey('US', $byCountry);
        self::assertArrayHasKey('DE', $byCountry);

        // US aggregated: gross=300, wht=45, tax=12
        self::assertTrue($byCountry['US']->grossDividendPLN->isEqualTo('300.00'));
        self::assertTrue($byCountry['US']->whtPaidPLN->isEqualTo('45.00'));
        self::assertTrue($byCountry['US']->polishTaxDue->isEqualTo('12.00'));

        // DE: gross=50, wht=13.25, tax=0
        self::assertTrue($byCountry['DE']->grossDividendPLN->isEqualTo('50.00'));
        self::assertTrue($byCountry['DE']->polishTaxDue->isEqualTo('0.00'));

        // Total dividend tax: 12 + 0 = 12
        self::assertTrue($calc->dividendTotalTaxDue()->isEqualTo('12.00'));
    }

    /**
     * Prior year loss deduction reduces taxable income.
     * Equity gain: 10000, loss deduction: 3000 => taxable: 7000
     */
    public function testApplyPriorYearLosses(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '20000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '10000.00')],
            TaxCategory::EQUITY,
        );

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'), // 50% of 6000
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $calc->applyPriorYearLosses(
            ranges: [$lossRange],
            chosenAmounts: [BigDecimal::of('3000.00')],
        );

        self::assertTrue($calc->equityLossDeduction()->isEqualTo('3000.00'));

        $calc->finalize();

        // taxable = 10000 - 3000 = 7000 (rounded to full zloty)
        self::assertTrue($calc->equityTaxableIncome()->isEqualTo('7000'));
        // tax = 7000 * 0.19 = 1330
        self::assertTrue($calc->equityTax()->isEqualTo('1330'));
    }

    /**
     * Crypto loss deduction is applied to crypto basket separately.
     */
    public function testApplyPriorYearLossesCrypto(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '50000.00', costBasis: '40000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '10000.00')],
            TaxCategory::CRYPTO,
        );

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::CRYPTO,
            lossYear: TaxYear::of(2024),
            originalAmount: BigDecimal::of('4000.00'),
            remainingAmount: BigDecimal::of('4000.00'),
            maxDeductionThisYear: BigDecimal::of('2000.00'),
            expiresInYear: TaxYear::of(2029),
            yearsRemaining: 4,
        );

        $calc->applyPriorYearLosses([$lossRange], [BigDecimal::of('2000.00')]);
        $calc->finalize();

        self::assertTrue($calc->cryptoLossDeduction()->isEqualTo('2000.00'));
        // taxable = 10000 - 2000 = 8000
        self::assertTrue($calc->cryptoTaxableIncome()->isEqualTo('8000'));
        // tax = 8000 * 0.19 = 1520
        self::assertTrue($calc->cryptoTax()->isEqualTo('1520'));

        // Equity unaffected
        self::assertTrue($calc->equityLossDeduction()->isZero());
    }

    /**
     * Rejects deduction exceeding max allowed.
     */
    public function testApplyPriorYearLossesRejectsExceedingMax(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds max allowed');

        $calc->applyPriorYearLosses([$lossRange], [BigDecimal::of('5000.00')]);
    }

    /**
     * Finalize: 19% tax, rounded per art. 63 ss 1 Ordynacji podatkowej.
     *
     * Equity gain: 10142.73 PLN
     * Taxable income (rounded): 10143
     * Tax: 10143 * 0.19 = 1927.17 → rounded to 1927
     */
    public function testFinalize(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '79000.00', costBasis: '68850.00', buyComm: '4.05', sellComm: '3.22', gainLoss: '10142.73')],
            TaxCategory::EQUITY,
        );

        $calc->finalize();

        // Taxable income: 10142.73 → rounded to 10143 (art. 63 ss 1: >= 50 groszy -> w gore)
        self::assertTrue($calc->equityTaxableIncome()->isEqualTo('10143'));
        // Tax: 10143 * 0.19 = 1927.17 → rounded to 1927
        self::assertTrue($calc->equityTax()->isEqualTo('1927'));
        self::assertTrue($calc->isFinalized());
    }

    /**
     * Finalize with loss — taxable income cannot be negative.
     */
    public function testFinalizeWithLossYieldsZeroTax(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '5000.00', costBasis: '8000.00', buyComm: '10.00', sellComm: '5.00', gainLoss: '-3015.00')],
            TaxCategory::EQUITY,
        );

        $calc->finalize();

        self::assertTrue($calc->equityTaxableIncome()->isZero());
        self::assertTrue($calc->equityTax()->isZero());
    }

    /**
     * Total tax = equity tax + dividend tax + crypto tax.
     */
    public function testTotalTaxDue(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        // Equity: gain 10000
        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '20000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '10000.00')],
            TaxCategory::EQUITY,
        );

        // Crypto: gain 5000
        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '15000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '5000.00')],
            TaxCategory::CRYPTO,
        );

        // Dividend: polish tax due 100 PLN
        $nbpRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-06-01'), '110/A/NBP/2025');
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('1000.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('90.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('100.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $calc->finalize();

        // equity tax: 10000 * 0.19 = 1900
        self::assertTrue($calc->equityTax()->isEqualTo('1900'));
        // crypto tax: 5000 * 0.19 = 950
        self::assertTrue($calc->cryptoTax()->isEqualTo('950'));
        // dividend tax: 100.00
        self::assertTrue($calc->dividendTotalTaxDue()->isEqualTo('100.00'));
        // total: 1900 + 100 + 950 = 2950
        self::assertTrue($calc->totalTaxDue()->isEqualTo('2950.00'));
    }

    /**
     * toSnapshot() includes isFinalized flag.
     */
    public function testSnapshotContainsIsFinalizedFlag(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $snapshotBefore = $calc->toSnapshot();
        self::assertFalse($snapshotBefore->isFinalized);

        $snapshotAfter = $calc->finalize();
        self::assertTrue($snapshotAfter->isFinalized);
    }

    /**
     * Cannot modify after finalize.
     */
    public function testCannotModifyAfterFinalize(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc->finalize();

        $this->expectException(\LogicException::class);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '1000.00', costBasis: '500.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '500.00')],
            TaxCategory::EQUITY,
        );
    }

    /**
     * Double finalize() must throw LogicException.
     */
    public function testDoubleFinalizationThrowsLogicException(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc->finalize();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already finalized');

        $calc->finalize();
    }

    /**
     * Art. 63 ss 1 edge case: 0.49 rounds down, 0.50 rounds up.
     */
    public function testRoundingEdgeCases(): void
    {
        // Gain = 100.49 → taxable = 100 (rounds down)
        $calc1 = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc1->addClosedPositions(
            [$this->closedPosition(proceeds: '200.49', costBasis: '100.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '100.49')],
            TaxCategory::EQUITY,
        );
        $calc1->finalize();
        self::assertTrue($calc1->equityTaxableIncome()->isEqualTo('100'));

        // Gain = 100.50 → taxable = 101 (rounds up)
        $calc2 = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc2->addClosedPositions(
            [$this->closedPosition(proceeds: '200.50', costBasis: '100.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '100.50')],
            TaxCategory::EQUITY,
        );
        $calc2->finalize();
        self::assertTrue($calc2->equityTaxableIncome()->isEqualTo('101'));
    }

    /**
     * P2-042: addClosedPositions with empty array is a noop — all buckets stay zero.
     */
    public function testAddClosedPositionsEmptyArrayIsNoop(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions([], TaxCategory::EQUITY);
        $calc->addClosedPositions([], TaxCategory::CRYPTO);

        self::assertTrue($calc->equityProceeds()->isZero());
        self::assertTrue($calc->equityCostBasis()->isZero());
        self::assertTrue($calc->equityCommissions()->isZero());
        self::assertTrue($calc->equityGainLoss()->isZero());
        self::assertTrue($calc->cryptoProceeds()->isZero());
        self::assertTrue($calc->cryptoCostBasis()->isZero());
        self::assertTrue($calc->cryptoCommissions()->isZero());
        self::assertTrue($calc->cryptoGainLoss()->isZero());
    }

    /**
     * P2-041: addDividendResult() after finalize() must throw LogicException.
     */
    public function testAddDividendResultAfterFinalizeThrowsLogicException(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc->finalize();

        $nbpRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-06-01'), '110/A/NBP/2025');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already finalized');

        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('100.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('15.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('4.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));
    }

    /**
     * P2-041: applyPriorYearLosses() after finalize() must throw LogicException.
     */
    public function testApplyPriorYearLossesAfterFinalizeThrowsLogicException(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);
        $calc->finalize();

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already finalized');

        $calc->applyPriorYearLosses([$lossRange], [BigDecimal::of('1000.00')]);
    }

    /**
     * P2-040: Loss deduction exceeding gain — taxable income clamped to 0.
     * Equity gain: 2000, loss deduction: 3000 => taxable: 0 (not -1000).
     */
    public function testApplyPriorYearLossesDeductionExceedsGainClampsToZero(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '12000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '2000.00')],
            TaxCategory::EQUITY,
        );

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $calc->applyPriorYearLosses([$lossRange], [BigDecimal::of('3000.00')]);
        $calc->finalize();

        // deduction (3000) > gain (2000) — taxable income clamped to 0
        self::assertTrue($calc->equityLossDeduction()->isEqualTo('3000.00'));
        self::assertTrue($calc->equityTaxableIncome()->isZero());
        self::assertTrue($calc->equityTax()->isZero());
    }

    /**
     * P3-004: Extreme large amount — 10M PLN equity position.
     * Verifies no overflow or precision loss in BigDecimal at scale.
     */
    public function testExtremeAmountTenMillionPLN(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(
                proceeds: '10000000.00',
                costBasis: '8000000.00',
                buyComm: '500.00',
                sellComm: '500.00',
                gainLoss: '1999000.00',
            )],
            TaxCategory::EQUITY,
        );

        $calc->finalize();

        self::assertTrue($calc->equityProceeds()->isEqualTo('10000000.00'));
        self::assertTrue($calc->equityCostBasis()->isEqualTo('8000000.00'));
        self::assertTrue($calc->equityGainLoss()->isEqualTo('1999000.00'));
        // taxable = 1999000, tax = 1999000 * 0.19 = 379810
        self::assertTrue($calc->equityTaxableIncome()->isEqualTo('1999000'));
        self::assertTrue($calc->equityTax()->isEqualTo('379810'));
    }

    /**
     * P3-005: Extreme small amount — 0.01 PLN gain.
     * Verifies rounding at the grosz boundary.
     */
    public function testExtremeAmountOnePenny(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(
                proceeds: '100.01',
                costBasis: '100.00',
                buyComm: '0.00',
                sellComm: '0.00',
                gainLoss: '0.01',
            )],
            TaxCategory::EQUITY,
        );

        $calc->finalize();

        // 0.01 rounds to 0 (art. 63 ss 1: < 0.50 rounds down)
        self::assertTrue($calc->equityTaxableIncome()->isZero());
        self::assertTrue($calc->equityTax()->isZero());
    }

    /**
     * Dual-path reconciliation passes when data is consistent:
     * gainLoss == proceeds - costBasis - commissions for each basket.
     */
    public function testReconciliationPassesForConsistentData(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        // Equity: 10000 - 8000 - (10 + 5) = 1985
        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '10000.00', costBasis: '8000.00', buyComm: '10.00', sellComm: '5.00', gainLoss: '1985.00')],
            TaxCategory::EQUITY,
        );

        // Crypto: 20000 - 15000 - (50 + 50) = 4900
        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '20000.00', costBasis: '15000.00', buyComm: '50.00', sellComm: '50.00', gainLoss: '4900.00')],
            TaxCategory::CRYPTO,
        );

        // Must not throw TaxReconciliationException
        $snapshot = $calc->finalize();
        self::assertTrue($snapshot->isFinalized);
    }

    /**
     * Dual-path reconciliation catches inconsistency:
     * gainLoss != proceeds - costBasis - commissions triggers exception.
     *
     * Uses reflection to corrupt equityGainLoss after adding consistent positions.
     */
    public function testReconciliationCatchesInconsistency(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '10000.00', costBasis: '8000.00', buyComm: '10.00', sellComm: '5.00', gainLoss: '1985.00')],
            TaxCategory::EQUITY,
        );

        // Corrupt internal state via reflection to simulate data inconsistency
        $ref = new \ReflectionProperty($calc, 'equityGainLoss');
        $ref->setValue($calc, BigDecimal::of('9999.99'));

        $this->expectException(TaxReconciliationException::class);
        $this->expectExceptionMessage("basket 'equity'");

        $calc->finalize();
    }

    // --- Helpers ---

    private function closedPosition(
        string $proceeds,
        string $costBasis,
        string $buyComm,
        string $sellComm,
        string $gainLoss,
    ): ClosedPosition {
        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-14'),
            '009/A/NBP/2025',
        );

        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('100'),
            costBasisPLN: BigDecimal::of($costBasis),
            proceedsPLN: BigDecimal::of($proceeds),
            buyCommissionPLN: BigDecimal::of($buyComm),
            sellCommissionPLN: BigDecimal::of($sellComm),
            gainLossPLN: BigDecimal::of($gainLoss),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-20'),
            buyNBPRate: $nbpRate,
            sellNBPRate: $nbpRate,
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
