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

/**
 * Tests that AnnualTaxCalculationService calls markUsedInYear on the CRUD port
 * when a non-zero deduction is applied, and does NOT call it when deduction is zero.
 *
 * P0-010: PriorYearLoss mutable after use
 */
final class AnnualTaxCalculationServiceMarkUsedTest extends TestCase
{
    private UserId $userId;

    private TaxYear $taxYear;

    protected function setUp(): void
    {
        $this->userId = UserId::generate();
        $this->taxYear = TaxYear::of(2025);
    }

    /**
     * When a non-zero deduction is applied, markUsedInYear must be called
     * with the correct (userId, lossYear, category, currentYear) arguments.
     */
    public function testMarkUsedInYearCalledWhenDeductionApplied(): void
    {
        $lossYear = TaxYear::of(2023);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->buildEquityPosition('10000.00')];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: $lossYear,
            originalAmount: BigDecimal::of('6000.00'),
            remainingAmount: BigDecimal::of('3000.00'),
            maxDeductionThisYear: BigDecimal::of('3000.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([$lossRange]);

        $priorYearLossCrud = $this->createMock(PriorYearLossCrudPort::class);
        $priorYearLossCrud
            ->expects(self::once())
            ->method('markUsedInYear')
            ->with(
                $this->userId,
                $lossYear->value,
                TaxCategory::EQUITY,
                $this->taxYear->value,
            );

        $service = new AnnualTaxCalculationService(
            $closedPositionQuery,
            $dividendQuery,
            $priorYearLossQuery,
            $priorYearLossCrud,
        );

        $service->calculate($this->userId, $this->taxYear);
    }

    /**
     * When deduction is zero (gain is zero/negative), markUsedInYear must NOT be called.
     */
    public function testMarkUsedInYearNotCalledWhenDeductionIsZero(): void
    {
        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    // Negative gain — clamped deduction = 0
                    return [$this->buildEquityPosition('-2000.00')];
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
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([$lossRange]);

        $priorYearLossCrud = $this->createMock(PriorYearLossCrudPort::class);
        $priorYearLossCrud
            ->expects(self::never())
            ->method('markUsedInYear');

        $service = new AnnualTaxCalculationService(
            $closedPositionQuery,
            $dividendQuery,
            $priorYearLossQuery,
            $priorYearLossCrud,
        );

        $service->calculate($this->userId, $this->taxYear);
    }

    /**
     * When no prior year losses exist, markUsedInYear is never called.
     */
    public function testMarkUsedInYearNotCalledWhenNoLosses(): void
    {
        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery->method('findByUserYearAndCategory')->willReturn([]);

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $priorYearLossQuery->method('findByUserAndYear')->willReturn([]);

        $priorYearLossCrud = $this->createMock(PriorYearLossCrudPort::class);
        $priorYearLossCrud
            ->expects(self::never())
            ->method('markUsedInYear');

        $service = new AnnualTaxCalculationService(
            $closedPositionQuery,
            $dividendQuery,
            $priorYearLossQuery,
            $priorYearLossCrud,
        );

        $service->calculate($this->userId, $this->taxYear);
    }

    private function buildEquityPosition(string $gainLoss): ClosedPosition
    {
        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );

        // Build proceeds/costBasis from gainLoss for consistency
        $proceeds = BigDecimal::of('100000.00');
        $cost = $proceeds->minus(BigDecimal::of($gainLoss));

        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('100'),
            costBasisPLN: $cost,
            proceedsPLN: $proceeds,
            buyCommissionPLN: BigDecimal::zero(),
            sellCommissionPLN: BigDecimal::zero(),
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
