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
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for AnnualTaxCalculation.
 * Targets: effectiveWHTRate ternary, dividedBy scale, crypto reconcile, dividendTotalTaxDue scale.
 */
final class AnnualTaxCalculationMutationTest extends TestCase
{
    private UserId $userId;

    private TaxYear $taxYear;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->taxYear = TaxYear::of(2025);
    }

    /**
     * Kills mutant #635: Ternary swap in effectiveWHTRate calculation.
     * When grossPLN is zero, effectiveWHTRate must be zero (not divide by zero).
     */
    public function testDividendWithZeroGrossReturnsZeroEffectiveWHTRate(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-06-01'),
            '110/A/NBP/2025',
        );

        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('0.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('0.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('0.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $byCountry = $calc->dividendsByCountry();
        self::assertArrayHasKey('US', $byCountry);
        self::assertTrue($byCountry['US']->effectiveWHTRate->isZero());
    }

    /**
     * Kills mutants #636-#637: dividedBy scale 6 changed to 5 or 7 in effectiveWHTRate.
     * WHT rate should be calculated at scale 6 precision.
     */
    public function testEffectiveWHTRateHasScale6Precision(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-06-01'),
            '110/A/NBP/2025',
        );

        // gross=300, wht=45 -> rate = 45/300 = 0.150000 (scale 6)
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('300.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('45.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('12.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $byCountry = $calc->dividendsByCountry();
        $rate = $byCountry['US']->effectiveWHTRate;
        self::assertSame(6, $rate->getScale());
        self::assertTrue($rate->isEqualTo('0.150000'));
    }

    /**
     * Kills mutant #638: MethodCallRemoval of crypto reconcile.
     * If crypto reconciliation is skipped, inconsistent crypto data should NOT be caught.
     * We verify it IS caught by corrupting crypto data.
     */
    public function testCryptoReconciliationDetectsInconsistency(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(
                proceeds: '20000.00',
                costBasis: '15000.00',
                buyComm: '50.00',
                sellComm: '50.00',
                gainLoss: '4900.00',
            )],
            TaxCategory::CRYPTO,
        );

        // Corrupt crypto state via reflection to simulate inconsistency
        $ref = new \ReflectionProperty($calc, 'cryptoGainLoss');
        $ref->setValue($calc, BigDecimal::of('9999.99'));

        $this->expectException(TaxReconciliationException::class);
        $this->expectExceptionMessage("basket 'crypto'");

        $calc->finalize();
    }

    /**
     * Kills mutant #639: MethodCallRemoval of recalculateDividendTotal().
     * Without recalculation, dividendTotalTaxDue would be stale.
     */
    public function testFinalizeDividendTotalIsRecalculated(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-06-01'),
            '110/A/NBP/2025',
        );

        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('1000.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('150.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('40.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $snapshot = $calc->finalize();

        // dividendTotalTaxDue must equal 40.00, not 0
        self::assertTrue($snapshot->dividendTotalTaxDue->isEqualTo('40.00'));
    }

    /**
     * Kills mutants #640-#641: dividendTotalTaxDue toScale(2) changed to toScale(1) or toScale(3).
     * The scale of dividendTotalTaxDue after finalize must be exactly 2.
     */
    public function testDividendTotalTaxDueHasScale2(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-06-01'),
            '110/A/NBP/2025',
        );

        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('333.33', CurrencyCode::PLN),
            whtPaidPLN: Money::of('49.9995', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('13.33', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $snapshot = $calc->finalize();

        self::assertSame(2, $snapshot->dividendTotalTaxDue->getScale());
    }

    /**
     * Kills mutant on negative deduction amount validation.
     */
    public function testApplyPriorYearLossesRejectsNegativeAmount(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $lossRange = new \App\TaxCalc\Domain\ValueObject\LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('6000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('negative');

        $calc->applyPriorYearLosses([$lossRange], [BigDecimal::of('-100.00')]);
    }

    /**
     * Kills mutant on mismatched ranges/amounts count.
     */
    public function testApplyPriorYearLossesRejectsMismatchedCounts(): void
    {
        $calc = AnnualTaxCalculation::create($this->userId, $this->taxYear);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must match');

        $calc->applyPriorYearLosses([], [BigDecimal::of('100.00')]);
    }

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
