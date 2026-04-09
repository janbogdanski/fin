<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Application;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Declaration\Domain\Service\AuditReportGenerator;
use App\Declaration\Infrastructure\Adapter\GetSourceTransactionDataAdapter;
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
use App\Tests\InMemory\InMemoryImportedTransactionRepository;
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
        $importedTransactions = new InMemoryImportedTransactionRepository();
        $lossCrud = new InMemoryPriorYearLossCrud(new MockClock(new \DateTimeImmutable('2026-04-09 11:00:00')));

        $aaplBuyId = TransactionId::generate();
        $aaplSellId = TransactionId::generate();
        $closedPositions->seed(
            $userId,
            $this->closedPosition(
                buyTransactionId: $aaplBuyId,
                sellTransactionId: $aaplSellId,
                isin: 'US0378331005',
                buyBroker: 'ibkr',
                sellBroker: 'degiro',
                costBasis: '1000.00',
                proceeds: '2000.00',
                gainLoss: '1000.00',
            ),
            TaxCategory::EQUITY,
        );
        $msftBuyId = TransactionId::generate();
        $msftSellId = TransactionId::generate();
        $closedPositions->seed(
            $userId,
            $this->closedPosition(
                buyTransactionId: $msftBuyId,
                sellTransactionId: $msftSellId,
                isin: 'US5949181045',
                buyBroker: 'revolut',
                sellBroker: 'revolut',
                costBasis: '500.00',
                proceeds: '700.00',
                gainLoss: '200.00',
            ),
            TaxCategory::EQUITY,
        );
        $importedTransactions->saveAll([
            $this->importedTransaction(
                userId: $userId,
                transactionId: $aaplBuyId,
                broker: 'ibkr',
                isin: 'US0378331005',
                symbol: 'AAPL',
                type: TransactionType::BUY,
                price: '100.00',
                tradeDate: '2025-01-10 15:30:00',
            ),
            $this->importedTransaction(
                userId: $userId,
                transactionId: $aaplSellId,
                broker: 'degiro',
                isin: 'US0378331005',
                symbol: 'AAPL',
                type: TransactionType::SELL,
                price: '200.00',
                tradeDate: '2025-06-15 15:30:00',
            ),
            $this->importedTransaction(
                userId: $userId,
                transactionId: $msftBuyId,
                broker: 'revolut',
                isin: 'US5949181045',
                symbol: 'MSFT',
                type: TransactionType::BUY,
                price: '50.00',
                tradeDate: '2025-01-10 15:30:00',
            ),
            $this->importedTransaction(
                userId: $userId,
                transactionId: $msftSellId,
                broker: 'revolut',
                isin: 'US5949181045',
                symbol: 'MSFT',
                type: TransactionType::SELL,
                price: '70.00',
                tradeDate: '2025-06-15 15:30:00',
            ),
        ]);

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
            new GetSourceTransactionDataAdapter($importedTransactions),
        );

        $reportData = $builder->build($userId, $taxYear, 'Jan', 'Kowalski');
        $html = (new AuditReportGenerator())->generate($reportData, new \DateTimeImmutable('2026-04-09 11:15:00'));

        self::assertSame('2700.00', $reportData->totalProceeds);
        self::assertSame('1500.00', $reportData->totalCosts);
        self::assertSame('1200.00', $reportData->totalGainLoss);
        self::assertSame('118.00', $reportData->totalTax);
        self::assertCount(2, $reportData->closedPositions);
        self::assertSame('AAPL', $reportData->closedPositions[0]->symbol);
        self::assertSame('ibkr', $reportData->closedPositions[0]->buyBroker);
        self::assertSame('degiro', $reportData->closedPositions[0]->sellBroker);
        self::assertSame('100.00', $reportData->closedPositions[0]->buyPricePerUnit);
        self::assertSame('USD', $reportData->closedPositions[0]->buyPriceCurrency);
        self::assertSame('200.00', $reportData->closedPositions[0]->sellPricePerUnit);
        self::assertSame('USD', $reportData->closedPositions[0]->sellPriceCurrency);

        self::assertStringContainsString('Broker kupna', $html);
        self::assertStringContainsString('Broker sprzedazy', $html);
        self::assertStringContainsString('Cena kupna', $html);
        self::assertStringContainsString('Cena sprzedazy', $html);
        self::assertStringContainsString('AAPL', $html);
        self::assertStringContainsString('100.00', $html);
        self::assertStringContainsString('200.00', $html);
        self::assertStringContainsString('ibkr', $html);
        self::assertStringContainsString('degiro', $html);
        self::assertStringContainsString('revolut', $html);
        self::assertStringContainsString('Podsumowanie per broker', $html);
        self::assertStringContainsString('Podatek nalezny ogolem', $html);
        self::assertStringContainsString('118.00', $html);
    }

    private function closedPosition(
        TransactionId $buyTransactionId,
        TransactionId $sellTransactionId,
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
            buyTransactionId: $buyTransactionId,
            sellTransactionId: $sellTransactionId,
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

    private function importedTransaction(
        UserId $userId,
        TransactionId $transactionId,
        string $broker,
        string $isin,
        string $symbol,
        TransactionType $type,
        string $price,
        string $tradeDate,
    ): ImportedTransaction {
        return new ImportedTransaction(
            id: $transactionId,
            userId: $userId,
            importBatchId: 'batch-1',
            broker: BrokerId::of($broker),
            isin: ISIN::fromString($isin),
            symbol: $symbol,
            transactionType: $type->value,
            date: new \DateTimeImmutable($tradeDate),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of($price, CurrencyCode::USD),
            commission: Money::of('0.00', CurrencyCode::USD),
            description: $type->value,
            contentHash: 'hash-' . $transactionId->toString(),
            createdAt: new \DateTimeImmutable('2025-01-01 00:00:00'),
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
