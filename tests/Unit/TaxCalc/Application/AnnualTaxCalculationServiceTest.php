<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class AnnualTaxCalculationServiceTest extends TestCase
{
    /**
     * Prior year equity losses should reduce taxable income.
     *
     * Scenario: 10000 PLN equity gain, 3000 PLN prior-year loss deduction.
     * Expected taxable income: 10000 - 3000 = 7000, tax = round(7000 * 0.19) = 1330.
     */
    public function testPriorYearLossesAreAppliedToEquity(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('90000.00', '100000.00', '0.00', '0.00', '10000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('3000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$lossRange]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        self::assertTrue($result->isFinalized());
        self::assertTrue(
            $result->equityLossDeduction()->isEqualTo('3000.00'),
            'equityLossDeduction should be 3000.00',
        );
        self::assertTrue(
            $result->equityTaxableIncome()->isEqualTo('7000'),
            'equityTaxableIncome should be 7000 (10000 - 3000)',
        );
        self::assertTrue(
            $result->equityTax()->isEqualTo('1330'),
            'equityTax should be 1330 (7000 * 0.19)',
        );
    }

    /**
     * Prior year crypto losses should reduce crypto taxable income.
     */
    public function testPriorYearLossesAreAppliedToCrypto(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::CRYPTO) {
                    return [$this->buildClosedPosition('5000.00', '8000.00', '0.00', '0.00', '3000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::CRYPTO,
            lossYear: TaxYear::of(2024),
            originalAmount: BigDecimal::of('1000.00'),
            remainingAmount: BigDecimal::of('1000.00'),
            maxDeductionThisYear: BigDecimal::of('1000.00'),
            expiresInYear: TaxYear::of(2029),
            yearsRemaining: 4,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$lossRange]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        self::assertTrue($result->isFinalized());
        self::assertTrue(
            $result->cryptoLossDeduction()->isEqualTo('1000.00'),
            'cryptoLossDeduction should be 1000.00',
        );
        self::assertTrue(
            $result->cryptoTaxableIncome()->isEqualTo('2000'),
            'cryptoTaxableIncome should be 2000 (3000 - 1000)',
        );
    }

    /**
     * When no prior year losses exist, behavior is backward compatible (zero deductions).
     */
    public function testNoPriorYearLossesIsBackwardCompatible(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('90000.00', '100000.00', '0.00', '0.00', '10000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        self::assertTrue($result->isFinalized());
        self::assertTrue($result->equityLossDeduction()->isZero());
        self::assertTrue(
            $result->equityTaxableIncome()->isEqualTo('10000'),
            'equityTaxableIncome should be 10000 (no deduction)',
        );
        self::assertTrue(
            $result->equityTax()->isEqualTo('1900'),
            'equityTax should be 1900 (10000 * 0.19)',
        );
    }

    /**
     * Loss deduction is clamped to current gain to prevent wasting deduction rights.
     *
     * Scenario: 2000 PLN equity gain, 5000 PLN prior-year loss with max deduction 3000.
     * Expected: deduction clamped to 2000 (the current gain), not 3000.
     */
    public function testLossDeductionIsClampedToCurrentGain(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('12000.00', '14000.00', '0.00', '0.00', '2000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('5000.00'),
            remainingAmount: BigDecimal::of('5000.00'),
            maxDeductionThisYear: BigDecimal::of('2500.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$lossRange]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        self::assertTrue($result->isFinalized());
        // Deduction clamped to gain (2000), not max allowed (2500)
        self::assertTrue(
            $result->equityLossDeduction()->isEqualTo('2000.00'),
            'equityLossDeduction should be clamped to gain of 2000.00, not max of 2500.00',
        );
        self::assertTrue(
            $result->equityTaxableIncome()->isEqualTo('0'),
            'equityTaxableIncome should be 0 (2000 - 2000)',
        );
        self::assertTrue(
            $result->equityTax()->isEqualTo('0'),
            'equityTax should be 0',
        );
    }

    /**
     * Loss deduction is NOT clamped when gain exceeds available deduction.
     */
    public function testLossDeductionNotClampedWhenGainSufficient(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('90000.00', '100000.00', '0.00', '0.00', '10000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('3000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$lossRange]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        // Full deduction applied — gain (10000) > max deduction (3000)
        self::assertTrue(
            $result->equityLossDeduction()->isEqualTo('3000.00'),
            'Full deduction should be applied when gain is sufficient',
        );
    }

    /**
     * When current gain is zero or negative, no deduction should be applied.
     */
    public function testNoDeductionWhenGainIsNegative(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('8000.00', '5000.00', '0.00', '0.00', '-3000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('3000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$lossRange]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery, $this->createStub(PriorYearLossCrudPort::class));
        $result = $service->calculate($userId, $taxYear);

        // No deduction when gain is negative
        self::assertTrue(
            $result->equityLossDeduction()->isZero(),
            'No deduction should be applied when current gain is negative',
        );
    }

    /**
     * P2-127: Multi-loss clamping — 3 prior-year loss ranges whose combined max deduction
     * exceeds the current gain. Each range must be allocated in order, clamped so that
     * the total deduction never exceeds the actual gain.
     *
     * Scenario:
     *   - Equity gain: 3000 PLN
     *   - Loss range A (2021): maxDeductionThisYear = 2000
     *   - Loss range B (2022): maxDeductionThisYear = 2000
     *   - Loss range C (2023): maxDeductionThisYear = 2000
     *   - Combined max = 6000, but gain = 3000
     *
     * Expected:
     *   - Range A uses 2000 (full — gain has 3000 available)
     *   - Range B uses 1000 (clamped — only 1000 gain remaining)
     *   - Range C uses 0   (no gain left)
     *   - Total deduction = 3000, taxableIncome = 0, tax = 0
     */
    public function testMultiLossClampingWhenGainSmallerThanSumOfLosses(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildClosedPosition('7000.00', '10000.00', '0.00', '0.00', '3000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $makeRange = fn (int $lossYear, string $max): LossDeductionRange => new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of($lossYear),
            originalAmount: BigDecimal::of($max),
            remainingAmount: BigDecimal::of($max),
            maxDeductionThisYear: BigDecimal::of($max),
            expiresInYear: TaxYear::of($lossYear + 5),
            yearsRemaining: 5,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([
            $makeRange(2021, '2000.00'), // uses 2000
            $makeRange(2022, '2000.00'), // uses 1000 (only 1000 gain left)
            $makeRange(2023, '2000.00'), // uses 0   (no gain left)
        ]);

        // Range A (2021) and B (2022) get non-zero deductions → must be locked.
        // Range C (2023) deduction = 0 → must NOT be locked (preserves deduction right for future years).
        $priorYearLossCrud = $this->createMock(PriorYearLossCrudPort::class);
        $priorYearLossCrud->expects(self::exactly(2))
            ->method('markUsedInYear')
            ->with(
                self::equalTo($userId),
                self::logicalOr(self::equalTo(2021), self::equalTo(2022)),
                self::equalTo(TaxCategory::EQUITY),
                self::equalTo(2025),
            );

        $service = new AnnualTaxCalculationService(
            $closedPositionQuery,
            $dividendQuery,
            $priorYearLossQuery,
            $priorYearLossCrud,
        );
        $result = $service->calculate($userId, $taxYear);

        self::assertTrue($result->isFinalized());
        self::assertTrue(
            $result->equityLossDeduction()->isEqualTo('3000.00'),
            'Total deduction must be clamped to 3000 (the actual gain), not 6000 (sum of max deductions)',
        );
        self::assertTrue(
            $result->equityTaxableIncome()->isEqualTo('0'),
            'Taxable income must be 0 when gain is fully offset by loss deductions',
        );
        self::assertTrue(
            $result->equityTax()->isEqualTo('0'),
            'Tax must be 0',
        );
    }

    private function buildClosedPosition(
        string $costBasis,
        string $proceeds,
        string $buyComm,
        string $sellComm,
        string $gainLoss,
    ): ClosedPosition {
        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
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
            buyDate: new \DateTimeImmutable('2025-03-15'),
            sellDate: new \DateTimeImmutable('2025-09-20'),
            buyNBPRate: $rate,
            sellNBPRate: $rate,
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
