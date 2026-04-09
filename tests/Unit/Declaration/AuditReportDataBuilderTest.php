<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Application\Port\SourceTransactionLookupPort;
use App\Declaration\Application\Port\TaxSummaryQueryPort;
use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\SourceTransactionSnapshot;
use App\Declaration\Infrastructure\Service\AuditReportDataBuilder;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuditReportDataBuilderTest extends TestCase
{
    private MockObject&ClosedPositionQueryPort $closedPositionQuery;

    private MockObject&DividendResultQueryPort $dividendResultQuery;

    private MockObject&PriorYearLossQueryPort $priorYearLossQuery;

    private MockObject&TaxSummaryQueryPort $taxSummaryQuery;

    private MockObject&SourceTransactionLookupPort $sourceTransactionLookup;

    private AuditReportDataBuilder $builder;

    protected function setUp(): void
    {
        $this->closedPositionQuery = $this->createMock(ClosedPositionQueryPort::class);
        $this->dividendResultQuery = $this->createMock(DividendResultQueryPort::class);
        $this->priorYearLossQuery = $this->createMock(PriorYearLossQueryPort::class);
        $this->taxSummaryQuery = $this->createMock(TaxSummaryQueryPort::class);
        $this->sourceTransactionLookup = $this->createMock(SourceTransactionLookupPort::class);

        $this->builder = new AuditReportDataBuilder(
            $this->closedPositionQuery,
            $this->dividendResultQuery,
            $this->priorYearLossQuery,
            $this->taxSummaryQuery,
            $this->sourceTransactionLookup,
        );
    }

    public function testBuildWithPositionsAndDividends(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $taxYear = TaxYear::of(2025);

        $this->closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturnCallback(function (UserId $uid, TaxYear $ty, TaxCategory $cat): array {
                if ($cat === TaxCategory::EQUITY) {
                    return [$this->createClosedPosition()];
                }

                return [];
            });

        $this->dividendResultQuery
            ->method('findByUserAndYear')
            ->willReturn([$this->createDividendResult()]);

        $this->priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([]);
        $this->taxSummaryQuery
            ->method('getTaxSummary')
            ->willReturn($this->createTaxSummary(totalTaxDue: '1988.00'));
        $this->sourceTransactionLookup
            ->expects(self::once())
            ->method('findByUserAndIds')
            ->with($userId, [
                '550e8400-e29b-41d4-a716-446655440001',
                '550e8400-e29b-41d4-a716-446655440002',
            ])
            ->willReturn([
                $this->createSourceTransactionSnapshot(
                    transactionId: '550e8400-e29b-41d4-a716-446655440001',
                    symbol: 'AAPL',
                    price: '170.25',
                    currency: CurrencyCode::USD,
                ),
                $this->createSourceTransactionSnapshot(
                    transactionId: '550e8400-e29b-41d4-a716-446655440002',
                    symbol: 'AAPL',
                    price: '195.10',
                    currency: CurrencyCode::USD,
                ),
            ]);

        $result = $this->builder->build($userId, $taxYear, 'Jan', 'Kowalski');

        self::assertInstanceOf(AuditReportData::class, $result);
        self::assertSame(2025, $result->taxYear);
        self::assertSame('Jan', $result->firstName);
        self::assertSame('Kowalski', $result->lastName);
        self::assertCount(1, $result->closedPositions);
        self::assertCount(1, $result->dividends);
        self::assertCount(0, $result->priorYearLosses);

        // Verify mapped closed position
        $pos = $result->closedPositions[0];
        self::assertSame('US0378331005', $pos->isin);
        self::assertSame('AAPL', $pos->symbol);
        self::assertSame('degiro', $pos->buyBroker);
        self::assertSame('degiro', $pos->sellBroker);
        self::assertSame('170.25', $pos->buyPricePerUnit);
        self::assertSame('USD', $pos->buyPriceCurrency);
        self::assertSame('195.10', $pos->sellPricePerUnit);
        self::assertSame('USD', $pos->sellPriceCurrency);
        self::assertSame('68850.00', $pos->costBasisPLN);
        self::assertSame('79000.00', $pos->proceedsPLN);

        // Verify mapped dividend
        $div = $result->dividends[0];
        self::assertSame(CountryCode::US, $div->countryCode);
        self::assertSame('1500.00', $div->grossAmountPLN);
    }

    public function testBuildWithEmptyDataReturnsZeroTotals(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $taxYear = TaxYear::of(2025);

        $this->closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturn([]);

        $this->dividendResultQuery
            ->method('findByUserAndYear')
            ->willReturn([]);

        $this->priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([]);
        $this->taxSummaryQuery
            ->method('getTaxSummary')
            ->willReturn($this->createTaxSummary(totalTaxDue: '0.00'));
        $this->sourceTransactionLookup
            ->expects(self::never())
            ->method('findByUserAndIds');

        $result = $this->builder->build($userId, $taxYear, 'Jan', 'Kowalski');

        self::assertSame(2025, $result->taxYear);
        self::assertCount(0, $result->closedPositions);
        self::assertCount(0, $result->dividends);
        self::assertSame('0.00', $result->totalProceeds);
        self::assertSame('0.00', $result->totalCosts);
        self::assertSame('0.00', $result->totalGainLoss);
        self::assertSame('0.00', $result->totalDividendGross);
        self::assertSame('0.00', $result->totalDividendWHT);
        self::assertSame('0.00', $result->totalTax);
    }

    public function testBuildWithPriorYearLosses(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $taxYear = TaxYear::of(2025);

        $this->closedPositionQuery
            ->method('findByUserYearAndCategory')
            ->willReturn([]);

        $this->dividendResultQuery
            ->method('findByUserAndYear')
            ->willReturn([]);

        $this->priorYearLossQuery
            ->method('findByUserAndYear')
            ->willReturn([$this->createLossRange()]);
        $this->taxSummaryQuery
            ->method('getTaxSummary')
            ->willReturn($this->createTaxSummary(totalTaxDue: '475.00'));
        $this->sourceTransactionLookup
            ->expects(self::never())
            ->method('findByUserAndIds');

        $result = $this->builder->build($userId, $taxYear, 'Jan', 'Kowalski');

        self::assertCount(1, $result->priorYearLosses);
        $loss = $result->priorYearLosses[0];
        self::assertSame(2023, $loss->year);
        self::assertSame('5000.00', $loss->amount);
        self::assertSame('2500.00', $loss->deducted);
        self::assertSame('475.00', $result->totalTax);
    }

    private function createClosedPosition(): ClosedPosition
    {
        return new ClosedPosition(
            buyTransactionId: TransactionId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            sellTransactionId: TransactionId::fromString('550e8400-e29b-41d4-a716-446655440002'),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('100'),
            costBasisPLN: BigDecimal::of('68850.00'),
            proceedsPLN: BigDecimal::of('79000.00'),
            buyCommissionPLN: BigDecimal::of('4.05'),
            sellCommissionPLN: BigDecimal::of('3.95'),
            gainLossPLN: BigDecimal::of('10142.00'),
            buyDate: new \DateTimeImmutable('2025-03-14'),
            sellDate: new \DateTimeImmutable('2025-09-19'),
            buyNBPRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-13'), '051/A/NBP/2025'),
            sellNBPRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('3.95'), new \DateTimeImmutable('2025-09-18'), '181/A/NBP/2025'),
            buyBroker: BrokerId::of('degiro'),
            sellBroker: BrokerId::of('degiro'),
        );
    }

    private function createDividendResult(): DividendTaxResult
    {
        return new DividendTaxResult(
            grossDividendPLN: Money::of('1500.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('225.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('60.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-06-14'), '115/A/NBP/2025'),
        );
    }

    private function createLossRange(): LossDeductionRange
    {
        return new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('5000.00'),
            remainingAmount: BigDecimal::of('5000.00'),
            maxDeductionThisYear: BigDecimal::of('2500.00'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );
    }

    private function createTaxSummary(string $totalTaxDue): \App\TaxCalc\Application\Query\TaxSummaryResult
    {
        return new \App\TaxCalc\Application\Query\TaxSummaryResult(
            taxYear: 2025,
            equityProceeds: '79000.00',
            equityCostBasis: '68850.00',
            equityCommissions: '8.00',
            equityGainLoss: '10142.00',
            equityLossDeduction: '0.00',
            equityTaxableIncome: '10142.00',
            equityTax: '1928',
            dividendsByCountry: [],
            dividendTotalTaxDue: '60.00',
            cryptoProceeds: '0.00',
            cryptoCostBasis: '0.00',
            cryptoCommissions: '0.00',
            cryptoGainLoss: '0.00',
            cryptoLossDeduction: '0.00',
            cryptoTaxableIncome: '0',
            cryptoTax: '0',
            totalTaxDue: $totalTaxDue,
        );
    }

    private function createSourceTransactionSnapshot(
        string $transactionId,
        string $symbol,
        string $price,
        CurrencyCode $currency,
    ): SourceTransactionSnapshot {
        return new SourceTransactionSnapshot(
            transactionId: $transactionId,
            symbol: $symbol,
            pricePerUnit: $price,
            priceCurrency: $currency->value,
        );
    }
}
