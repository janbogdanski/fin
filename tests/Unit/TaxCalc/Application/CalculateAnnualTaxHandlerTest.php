<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\CalculateAnnualTax;
use App\TaxCalc\Application\Command\CalculateAnnualTaxHandler;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for CalculateAnnualTaxHandler.
 *
 * Verifies orchestration: handler loads data from ports, builds
 * AnnualTaxCalculation, adds positions per category, finalizes.
 */
final class CalculateAnnualTaxHandlerTest extends TestCase
{
    /**
     * Handler creates AnnualTaxCalculation, adds equity positions, and finalizes.
     *
     * Uses a single ClosedPosition with known values to verify the aggregate is correctly wired.
     * costBasis=68850, proceeds=79000, commissions=8.00, gainLoss=10142.00
     * Expected tax = round(10142 * 0.19) = round(1926.98) = 1927
     */
    public function testHandlerCreatesCalculationWithEquityPositions(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPosition = $this->buildClosedPosition(
            costBasis: '68850.00',
            proceeds: '79000.00',
            buyComm: '4.05',
            sellComm: '3.95',
            gainLoss: '10142.00',
        );

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat) use ($userId, $taxYear, $closedPosition): array {
                // Only return data for EQUITY category
                if ($cat === TaxCategory::EQUITY) {
                    return [$closedPosition];
                }

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery
            ->method('findByUserAndYear')
            ->willReturn([]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery);
        $handler = new CalculateAnnualTaxHandler($service);
        $command = new CalculateAnnualTax($userId, $taxYear);

        $result = $handler($command);

        // Handler must finalize the calculation
        self::assertTrue($result->isFinalized(), 'Calculation must be finalized');

        // Equity values from the closed position
        self::assertTrue(
            $result->equityProceeds()->isEqualTo('79000.00'),
            'equityProceeds should be 79000.00',
        );
        self::assertTrue(
            $result->equityCostBasis()->isEqualTo('68850.00'),
            'equityCostBasis should be 68850.00',
        );
        self::assertTrue(
            $result->equityGainLoss()->isEqualTo('10142.00'),
            'equityGainLoss should be 10142.00',
        );

        // Tax calculation: round(10142 * 0.19) = round(1926.98) = 1927
        self::assertTrue(
            $result->equityTax()->isEqualTo('1927'),
            'equityTax should be 1927',
        );

        // No crypto, no dividends
        self::assertTrue($result->cryptoProceeds()->isZero());
        self::assertTrue($result->dividendTotalTaxDue()->isZero());

        // Total = equity tax only
        self::assertTrue(
            $result->totalTaxDue()->isEqualTo('1927'),
            'totalTaxDue should be 1927',
        );
    }

    /**
     * Handler with no positions at all still creates a finalized calculation with zeros.
     */
    public function testHandlerWithNoPositionsReturnsZeroTax(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery->method('findByUserYearAndCategory')->willReturn([]);

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery);
        $handler = new CalculateAnnualTaxHandler($service);
        $result = $handler(new CalculateAnnualTax($userId, $taxYear));

        self::assertTrue($result->isFinalized());
        self::assertTrue($result->totalTaxDue()->isZero());
        self::assertTrue($result->equityTax()->isZero());
        self::assertTrue($result->cryptoTax()->isZero());
    }

    /**
     * Handler with dividend results adds them to the calculation.
     */
    public function testHandlerWithDividendResults(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery->method('findByUserYearAndCategory')->willReturn([]);

        // Dividend: gross 100 PLN from US, 15% WHT, Polish tax due = max(0, 100*0.19 - 15) = 4.00
        $dividendResult = new DividendTaxResult(
            grossDividendPLN: Money::of('100.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('15.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('4.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025'),
        );

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([$dividendResult]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery);
        $handler = new CalculateAnnualTaxHandler($service);
        $result = $handler(new CalculateAnnualTax($userId, $taxYear));

        self::assertTrue($result->isFinalized());

        // Dividend tax due = 4.00
        self::assertTrue(
            $result->dividendTotalTaxDue()->isEqualTo('4.00'),
            'dividendTotalTaxDue: max(0, 100*0.19 - 15) = 4.00',
        );

        $usSummary = $result->dividendsByCountry()['US'] ?? null;
        self::assertNotNull($usSummary);
        self::assertTrue($usSummary->grossDividendPLN->isEqualTo('100.00'));
        self::assertTrue($usSummary->whtPaidPLN->isEqualTo('15.00'));
    }

    /**
     * Handler iterates ALL TaxCategory cases to load positions.
     */
    public function testHandlerQueriesAllTaxCategories(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $queriedCategories = [];

        $closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat) use (&$queriedCategories): array {
                $queriedCategories[] = $cat;

                return [];
            });

        $dividendQuery = $this->createMock(DividendResultQueryPort::class);
        $dividendQuery->method('findByUserAndYear')->willReturn([]);

        $service = new AnnualTaxCalculationService($closedPositionQuery, $dividendQuery);
        $handler = new CalculateAnnualTaxHandler($service);
        $handler(new CalculateAnnualTax($userId, $taxYear));

        // Handler must query ALL TaxCategory cases (EQUITY, DERIVATIVE, CRYPTO)
        self::assertCount(
            count(TaxCategory::cases()),
            $queriedCategories,
            'Handler must query all TaxCategory cases',
        );
        self::assertContains(TaxCategory::EQUITY, $queriedCategories);
        self::assertContains(TaxCategory::DERIVATIVE, $queriedCategories);
        self::assertContains(TaxCategory::CRYPTO, $queriedCategories);
    }

    private function buildClosedPosition(
        string $costBasis,
        string $proceeds,
        string $buyComm,
        string $sellComm,
        string $gainLoss,
    ): ClosedPosition {
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025');

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
