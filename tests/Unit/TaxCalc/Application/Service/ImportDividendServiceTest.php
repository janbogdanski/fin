<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultRepositoryPort;
use App\TaxCalc\Application\Service\ImportDividendService;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\DividendTaxService;
use App\TaxCalc\Domain\Service\UPORegistry;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ImportDividendServiceTest extends TestCase
{
    private ImportDividendService $service;

    private ExchangeRateProviderInterface&MockObject $rateProvider;

    private DividendResultRepositoryPort&MockObject $repository;

    protected function setUp(): void
    {
        $this->rateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $this->repository = $this->createMock(DividendResultRepositoryPort::class);
        $this->repository->method('transactional')->willReturnCallback(
            fn (callable $callback) => $callback(),
        );

        $dividendTaxService = new DividendTaxService(new UPORegistry(), new CurrencyConverter());

        $this->service = new ImportDividendService(
            $dividendTaxService,
            $this->rateProvider,
            $this->repository,
        );
    }

    /**
     * AC1: DIVIDEND + WHT transactions -> DividendTaxService calculates, results saved
     *
     * Scenario: US dividend $100, WHT 15%
     * Rate: 4.0 PLN/USD
     * grossPLN = 100 * 4.0 = 400
     * whtPaidPLN = 400 * 0.15 = 60
     * polishTax = 400 * 0.19 = 76
     * taxDue = 76 - 60 = 16
     */
    public function testDividendWithWhtPairProducesResult(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $userId = UserId::generate();

        $this->repository
            ->expects(self::once())
            ->method('deleteByUserAndYear')
            ->with($userId, TaxYear::of(2025));

        $this->repository
            ->expects(self::once())
            ->method('saveAll');

        $dividend = $this->createDividendTx('2025-06-15', '100.00', 'US');
        $wht = $this->createWhtTx('2025-06-15', '15.00', 'US');

        $results = $this->service->process([$dividend, $wht], $userId, TaxYear::of(2025));

        self::assertCount(1, $results);
        $result = $results[0];
        self::assertInstanceOf(DividendTaxResult::class, $result);
        self::assertTrue($result->grossDividendPLN->amount()->isEqualTo('400'));
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('16'));
        self::assertTrue($result->sourceCountry->equals(CountryCode::US));
    }

    public function testBuyAndSellTransactionsAreIgnored(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $buy = $this->createBuySellTx($isin, TransactionType::BUY, '2025-06-15');
        $sell = $this->createBuySellTx($isin, TransactionType::SELL, '2025-07-15');

        $this->repository
            ->expects(self::once())
            ->method('deleteByUserAndYear');

        $this->repository
            ->expects(self::once())
            ->method('saveAll')
            ->with(self::anything(), self::anything(), self::callback(
                fn (array $results): bool => $results === [],
            ));

        $results = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($results);
    }

    /**
     * Dividend without matching WHT -> WHT rate = 0 (no tax withheld).
     */
    public function testDividendWithoutWhtUsesZeroRate(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $dividend = $this->createDividendTx('2025-06-15', '100.00', 'US');

        $results = $this->service->process([$dividend], UserId::generate(), TaxYear::of(2025));

        self::assertCount(1, $results);
        // No WHT -> full 19% due: 400 * 0.19 = 76
        self::assertTrue($results[0]->polishTaxDue->amount()->isEqualTo('76'));
    }

    /**
     * Multiple dividends from different countries.
     */
    public function testMultipleDividendsFromDifferentCountries(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $usDividend = $this->createDividendTx('2025-06-15', '100.00', 'US');
        $usWht = $this->createWhtTx('2025-06-15', '15.00', 'US');
        $gbDividend = $this->createDividendTx('2025-07-15', '200.00', 'GB');
        $gbWht = $this->createWhtTx('2025-07-15', '30.00', 'GB');

        $results = $this->service->process(
            [$usDividend, $usWht, $gbDividend, $gbWht],
            UserId::generate(),
            TaxYear::of(2025),
        );

        self::assertCount(2, $results);
    }

    /**
     * AC4: Only dividends for the target tax year are processed.
     */
    public function testDividendsFilteredByTaxYear(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $dividend2024 = $this->createDividendTx('2024-06-15', '100.00', 'US');
        $wht2024 = $this->createWhtTx('2024-06-15', '15.00', 'US');
        $dividend2025 = $this->createDividendTx('2025-06-15', '200.00', 'US');
        $wht2025 = $this->createWhtTx('2025-06-15', '30.00', 'US');

        $results = $this->service->process(
            [$dividend2024, $wht2024, $dividend2025, $wht2025],
            UserId::generate(),
            TaxYear::of(2025),
        );

        // Only 2025 dividend should be processed
        self::assertCount(1, $results);
        self::assertTrue($results[0]->grossDividendPLN->amount()->isEqualTo('800'));
    }

    /**
     * AC4: Re-import dedup -- deleteByUserAndYear is called before saveAll.
     */
    public function testReimportDeletesExistingResultsBeforeSave(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        // Assert delete is called BEFORE save
        $callOrder = [];

        $this->repository
            ->expects(self::once())
            ->method('deleteByUserAndYear')
            ->with($userId, $taxYear)
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'delete';
            });

        $this->repository
            ->expects(self::once())
            ->method('saveAll')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'save';
            });

        $dividend = $this->createDividendTx('2025-06-15', '100.00', 'US');
        $wht = $this->createWhtTx('2025-06-15', '15.00', 'US');

        $this->service->process([$dividend, $wht], $userId, $taxYear);

        self::assertSame(['delete', 'save'], $callOrder);
    }

    /**
     * WHT rate is calculated from WHT amount / gross dividend amount.
     */
    public function testWhtRateCalculatedFromAmounts(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        // $100 dividend, $30 WHT -> 30% effective rate (should be capped to UPO 15%)
        $dividend = $this->createDividendTx('2025-06-15', '100.00', 'US');
        $wht = $this->createWhtTx('2025-06-15', '30.00', 'US');

        $results = $this->service->process([$dividend, $wht], UserId::generate(), TaxYear::of(2025));

        self::assertCount(1, $results);
        // WHT rate reported as actual 30%
        self::assertTrue($results[0]->whtRate->isEqualTo('0.3'));
        // whtPaidPLN = 400 * 0.30 = 120
        self::assertTrue($results[0]->whtPaidPLN->amount()->isEqualTo('120'));
        // Tax due = 76 - 60 (capped to UPO 15%) = 16
        self::assertTrue($results[0]->polishTaxDue->amount()->isEqualTo('16'));
    }

    /**
     * Finding #9: Dividend with null ISIN is gracefully skipped (not thrown).
     */
    public function testDividendWithNullIsinIsSkipped(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.0000');
        $this->rateProvider->method('getRateForDate')->willReturn($rate);

        $dividendNoIsin = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: 'UNKNOWN',
            type: TransactionType::DIVIDEND,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: Money::of('100.00', CurrencyCode::USD),
            commission: Money::zero(CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: 'Dividend without ISIN',
            rawData: [],
        );

        $results = $this->service->process([$dividendNoIsin], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($results, 'Dividend with null ISIN should be skipped');
    }

    private function createDividendTx(string $date, string $amount, string $countryCode): NormalizedTransaction
    {
        $isin = $this->isinForCountry($countryCode);

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'AAPL',
            type: TransactionType::DIVIDEND,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of('1'),
            pricePerUnit: Money::of($amount, CurrencyCode::USD),
            commission: Money::zero(CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: sprintf('Dividend from %s', $countryCode),
            rawData: [],
        );
    }

    private function createWhtTx(string $date, string $amount, string $countryCode): NormalizedTransaction
    {
        $isin = $this->isinForCountry($countryCode);

        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'AAPL',
            type: TransactionType::WITHHOLDING_TAX,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of('1'),
            pricePerUnit: Money::of($amount, CurrencyCode::USD),
            commission: Money::zero(CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: sprintf('WHT from %s', $countryCode),
            rawData: [],
        );
    }

    private function createBuySellTx(ISIN $isin, TransactionType $type, string $date): NormalizedTransaction
    {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'AAPL',
            type: $type,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('100.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: 'Test',
            rawData: [],
        );
    }

    private function isinForCountry(string $countryCode): ISIN
    {
        return match ($countryCode) {
            'US' => ISIN::fromString('US0378331005'),
            'GB' => ISIN::fromString('GB0002374006'),
            default => ISIN::fromString('US0378331005'),
        };
    }

    private function nbpRate(CurrencyCode $currency, string $rate): NBPRate
    {
        return NBPRate::create(
            $currency,
            BigDecimal::of($rate),
            new \DateTimeImmutable('2025-06-14'),
            '120/A/NBP/2025',
        );
    }
}
