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

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery);
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

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery);
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

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery, $priorYearLossQuery);
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
