<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Application;

use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Declaration\Infrastructure\Adapter\GetTaxSummaryAdapter;
use App\Declaration\Infrastructure\Service\AuditReportDataBuilder;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\InMemory\InMemoryClosedPositionQueryAdapter;
use App\Tests\InMemory\InMemoryDividendResultAdapter;
use App\Tests\InMemory\InMemoryPriorYearLossCrud;
use App\Tests\InMemory\InMemoryPriorYearLossQueryAdapter;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AuditReportProcessTest extends TestCase
{
    public function testAuditReportPreservesCrossBrokerProvenanceAndUsesSummaryTax(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);
        $closedPositions = new InMemoryClosedPositionQueryAdapter();
        $dividends = new InMemoryDividendResultAdapter();
        $lossCrud = new InMemoryPriorYearLossCrud(new MockClock(new \DateTimeImmutable('2026-04-09 11:00:00')));

        $closedPositions->seed(
            $userId,
            $this->closedPosition(
                isin: 'US0378331005',
                buyBroker: 'ibkr',
                sellBroker: 'degiro',
                costBasis: '1000.00',
                proceeds: '2000.00',
                gainLoss: '1000.00',
            ),
            TaxCategory::EQUITY,
        );
        $closedPositions->seed(
            $userId,
            $this->closedPosition(
                isin: 'US5949181045',
                buyBroker: 'revolut',
                sellBroker: 'revolut',
                costBasis: '500.00',
                proceeds: '700.00',
                gainLoss: '200.00',
            ),
            TaxCategory::EQUITY,
        );

        $dividends->saveAll($userId, $taxYear, [
            $this->usDividend('100.00', '15.00', '4.00'),
        ]);

        $lossCrud->save(new SavePriorYearLoss(
            userId: $userId,
            lossYear: 2023,
            taxCategory: TaxCategory::EQUITY,
            amount: BigDecimal::of('1200.00'),
        ));

        $summaryQuery = new GetTaxSummaryAdapter(new GetTaxSummaryHandler(
            new AnnualTaxCalculationService(
                $closedPositions,
                $dividends,
                new InMemoryPriorYearLossQueryAdapter($lossCrud),
                $lossCrud,
            ),
        ));

        $builder = new AuditReportDataBuilder(
            $closedPositions,
            $dividends,
            new InMemoryPriorYearLossQueryAdapter($lossCrud),
            $summaryQuery,
        );

        $reportData = $builder->build($userId, $taxYear, 'Jan', 'Kowalski');
        $html = (new AuditReportGenerator())->generate($reportData, new \DateTimeImmutable('2026-04-09 11:15:00'));

        self::assertSame('2700.00', $reportData->totalProceeds);
        self::assertSame('1500.00', $reportData->totalCosts);
        self::assertSame('1200.00', $reportData->totalGainLoss);
        self::assertSame('118.00', $reportData->totalTax);
        self::assertCount(2, $reportData->closedPositions);
        self::assertSame('ibkr', $reportData->closedPositions[0]->buyBroker);
        self::assertSame('degiro', $reportData->closedPositions[0]->sellBroker);

        self::assertStringContainsString('Broker kupna', $html);
        self::assertStringContainsString('Broker sprzedazy', $html);
        self::assertStringContainsString('ibkr', $html);
        self::assertStringContainsString('degiro', $html);
        self::assertStringContainsString('revolut', $html);
        self::assertStringContainsString('Podsumowanie per broker', $html);
        self::assertStringContainsString('Podatek nalezny ogolem', $html);
        self::assertStringContainsString('118.00', $html);
    }

    private function closedPosition(
        string $isin,
        string $buyBroker,
        string $sellBroker,
        string $costBasis,
        string $proceeds,
        string $gainLoss,
    ): ClosedPosition {
        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.0000'),
            new \DateTimeImmutable('2025-06-10'),
            '110/A/NBP/2025',
        );

        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString($isin),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of($costBasis),
            proceedsPLN: BigDecimal::of($proceeds),
            buyCommissionPLN: BigDecimal::zero(),
            sellCommissionPLN: BigDecimal::zero(),
            gainLossPLN: BigDecimal::of($gainLoss),
            buyDate: new \DateTimeImmutable('2025-01-10'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: $rate,
            sellNBPRate: $rate,
            buyBroker: BrokerId::of($buyBroker),
            sellBroker: BrokerId::of($sellBroker),
        );
    }

    private function usDividend(string $gross, string $wht, string $polishTaxDue): DividendTaxResult
    {
        return new DividendTaxResult(
            grossDividendPLN: Money::of($gross, CurrencyCode::PLN),
            whtPaidPLN: Money::of($wht, CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of($polishTaxDue, CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: NBPRate::create(
                CurrencyCode::USD,
                BigDecimal::of('4.0000'),
                new \DateTimeImmutable('2025-06-14'),
                '114/A/NBP/2025',
            ),
        );
    }
}
