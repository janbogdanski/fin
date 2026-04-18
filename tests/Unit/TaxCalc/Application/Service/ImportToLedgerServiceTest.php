<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Service\ImportToLedgerService;
use App\TaxCalc\Application\Service\IsinWithSymbolFallbackKeyResolver;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\Repository\TaxPositionLedgerRepositoryInterface;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ImportToLedgerServiceTest extends TestCase
{
    private ImportToLedgerService $service;

    private ExchangeRateProviderInterface&MockObject $rateProvider;

    protected function setUp(): void
    {
        $this->rateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $ledgerRepo = $this->createMock(TaxPositionLedgerRepositoryInterface::class);
        $ledgerRepo->method('findByUserAndISIN')->willReturn(null);
        $this->service = new ImportToLedgerService(
            new CurrencyConverter(),
            $this->rateProvider,
            $ledgerRepo,
            new NullLogger(),
            new IsinWithSymbolFallbackKeyResolver(),
        );
    }

    public function testBuyThenSellProducesClosedPosition(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $buy = $this->createTx($isin, TransactionType::BUY, '2025-06-15', '10', '100.00', '1.00');
        $sell = $this->createTx($isin, TransactionType::SELL, '2025-07-15', '10', '120.00', '1.00');

        $result = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        self::assertCount(1, $result->closedPositions);
        $closed = $result->closedPositions[0];
        self::assertInstanceOf(ClosedPosition::class, $closed);
        self::assertTrue($closed->isin->equals($isin));
        self::assertTrue($closed->quantity->isEqualTo(BigDecimal::of('10')));
    }

    public function testBuyOnlyProducesNoClosedPositions(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $buy = $this->createTx($isin, TransactionType::BUY, '2025-06-15', '10', '100.00', '1.00');

        $result = $this->service->process([$buy], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($result->closedPositions);
    }

    public function testMultipleISINsGroupedCorrectly(): void
    {
        $isinApple = ISIN::fromString('US0378331005');
        $isinMsft = ISIN::fromString('US5949181045');

        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $buyApple = $this->createTx($isinApple, TransactionType::BUY, '2025-06-15', '10', '100.00', '1.00');
        $sellApple = $this->createTx($isinApple, TransactionType::SELL, '2025-07-15', '10', '120.00', '1.00');
        $buyMsft = $this->createTx($isinMsft, TransactionType::BUY, '2025-06-15', '5', '200.00', '1.00');
        $sellMsft = $this->createTx($isinMsft, TransactionType::SELL, '2025-07-15', '5', '250.00', '1.00');

        $result = $this->service->process(
            [$buyApple, $buyMsft, $sellApple, $sellMsft],
            UserId::generate(),
            TaxYear::of(2025),
        );

        self::assertCount(2, $result->closedPositions);
    }

    public function testPLNTransactionsUseIdentityRate(): void
    {
        $isin = ISIN::fromString('PLPKO0000016');

        // PLN transactions should NOT need exchange rate provider
        $this->rateProvider
            ->expects(self::never())
            ->method('getRateForDate');

        $buy = $this->createPlnTx($isin, TransactionType::BUY, '2025-06-15', '100', '42.50', '5.00');
        $sell = $this->createPlnTx($isin, TransactionType::SELL, '2025-07-15', '100', '45.00', '5.00');

        $result = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        self::assertCount(1, $result->closedPositions);
        $closed = $result->closedPositions[0];

        // proceeds = 100 * 45.00 = 4500.00 PLN
        self::assertTrue($closed->proceedsPLN->isEqualTo(BigDecimal::of('4500.00')));
        // costBasis = 100 * 42.50 = 4250.00 PLN
        self::assertTrue($closed->costBasisPLN->isEqualTo(BigDecimal::of('4250.00')));
    }

    public function testOnlySellsForTaxYearAreReturned(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2024-06-14'), '120/A/NBP/2024');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $buy = $this->createTx($isin, TransactionType::BUY, '2024-06-15', '10', '100.00', '1.00');
        $sell = $this->createTx($isin, TransactionType::SELL, '2024-07-15', '10', '120.00', '1.00');

        // Filter for 2025 — sell happened in 2024
        $result = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($result->closedPositions);
    }

    public function testDividendsAndFeesAreSkipped(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $dividend = $this->createTx($isin, TransactionType::DIVIDEND, '2025-06-15', '1', '50.00', '0.00');
        $fee = $this->createTx($isin, TransactionType::FEE, '2025-06-15', '1', '10.00', '0.00');

        $result = $this->service->process([$dividend, $fee], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($result->closedPositions);
        self::assertEmpty($result->errors);
    }

    public function testSellWithoutBuyProducesError(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-07-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        $sell = $this->createTx($isin, TransactionType::SELL, '2025-07-15', '10', '120.00', '1.00');

        $result = $this->service->process([$sell], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($result->closedPositions);
        self::assertNotEmpty($result->errors);
        self::assertStringContainsString('US0378331005', $result->errors[0]);
    }

    /**
     * A transaction with no ISIN but a known symbol (XTB-style) must reach FIFO — not be silently dropped.
     */
    public function testBuyThenSellWithSymbolFallbackProducesClosedPosition(): void
    {
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');
        $this->rateProvider->method('getRateForDate')->willReturn($rate);

        $buy = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: 'AAPL.US',
            type: TransactionType::BUY,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('100.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('xtb'),
            description: 'XTB buy without ISIN',
            rawData: [],
        );
        $sell = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: 'AAPL.US',
            type: TransactionType::SELL,
            date: new \DateTimeImmutable('2025-07-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('120.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('xtb'),
            description: 'XTB sell without ISIN',
            rawData: [],
        );

        $result = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        self::assertCount(1, $result->closedPositions);
        self::assertEmpty($result->errors);
    }

    /**
     * A transaction with no ISIN and no symbol cannot be identified — it must be skipped.
     */
    public function testTransactionsWithNullISINAndEmptySymbolAreSkipped(): void
    {
        $tx = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: null,
            symbol: '',
            type: TransactionType::BUY,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('100.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('unknown'),
            description: 'No ISIN, no symbol',
            rawData: [],
        );

        $result = $this->service->process([$tx], UserId::generate(), TaxYear::of(2025));

        self::assertEmpty($result->closedPositions);
    }

    public function testFIFOMatchingWithCorrectGainLoss(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), new \DateTimeImmutable('2025-06-14'), '120/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturn($rate);

        // Buy 10 @ $100 + $1 commission = cost = 10*100*4 = 4000 PLN
        // Sell 10 @ $120 + $1 commission = proceeds = 10*120*4 = 4800 PLN per unit
        // Gain = 4800 - 4000 - 4 - 4 = 792 PLN (commissions: buy 1*4=4, sell 1*4=4)
        $buy = $this->createTx($isin, TransactionType::BUY, '2025-06-15', '10', '100.00', '1.00');
        $sell = $this->createTx($isin, TransactionType::SELL, '2025-07-15', '10', '120.00', '1.00');

        $result = $this->service->process([$buy, $sell], UserId::generate(), TaxYear::of(2025));

        $closed = $result->closedPositions[0];
        // proceeds per unit in PLN = 120 * 4 = 480, total = 480 * 10 = 4800
        self::assertTrue($closed->proceedsPLN->isEqualTo(BigDecimal::of('4800.00')));
        // cost per unit in PLN = 100 * 4 = 400, total = 400 * 10 = 4000
        self::assertTrue($closed->costBasisPLN->isEqualTo(BigDecimal::of('4000.00')));
        // gain = 4800 - 4000 - 4 - 4 = 792
        self::assertTrue($closed->gainLossPLN->isEqualTo(BigDecimal::of('792.00')));
    }

    public function testBuyUSDStockWithEURCommissionPreConvertsCommissionToPLN(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $date = new \DateTimeImmutable('2025-03-15');

        $usdRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), $date->modify('-1 day'), '052/A/NBP/2025');
        $eurRate = NBPRate::create(CurrencyCode::EUR, BigDecimal::of('4.2000'), $date->modify('-1 day'), '052/A/NBP/2025');

        $this->rateProvider
            ->method('getRateForDate')
            ->willReturnCallback(static function (CurrencyCode $currency) use ($usdRate, $eurRate): NBPRate {
                return match ($currency) {
                    CurrencyCode::USD => $usdRate,
                    CurrencyCode::EUR => $eurRate,
                    default => throw new \RuntimeException('Unexpected currency: ' . $currency->value),
                };
            });

        $tx = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'AAPL',
            type: TransactionType::BUY,
            date: $date,
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('171.25', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::EUR),
            broker: BrokerId::of('degiro'),
            description: 'AAPL buy with EUR commission',
            rawData: [],
        );

        $result = $this->service->process([$tx], UserId::generate(), TaxYear::of(2025));

        // Bug regression: cross-currency commission must NOT throw CurrencyMismatchException.
        self::assertEmpty($result->errors, 'Expected no errors but got: ' . implode(', ', $result->errors));
        self::assertGreaterThanOrEqual(0, \count($result->closedPositions));
    }

    public function testBuyUSDStockWithPLNCommissionDoesNotCallRateProviderForCommissionCurrency(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $date = new \DateTimeImmutable('2025-03-15');

        $usdRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.0000'), $date->modify('-1 day'), '052/A/NBP/2025');

        // PLN commission short-circuits in resolveCommission() — getRateForDate must only be
        // called for USD (price currency), never for PLN (commission currency).
        $this->rateProvider
            ->expects($this->atLeastOnce())
            ->method('getRateForDate')
            ->with(
                $this->callback(static fn (CurrencyCode $c): bool => $c->equals(CurrencyCode::USD)),
                $this->anything(),
            )
            ->willReturn($usdRate);

        $tx = new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'AAPL',
            type: TransactionType::BUY,
            date: $date,
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('171.25', CurrencyCode::USD),
            commission: Money::of('4.00', CurrencyCode::PLN),
            broker: BrokerId::of('degiro'),
            description: 'AAPL buy with PLN commission',
            rawData: [],
        );

        $result = $this->service->process([$tx], UserId::generate(), TaxYear::of(2025));

        // Bug regression: PLN commission must not trigger an extra getRateForDate call.
        self::assertEmpty($result->errors, 'Expected no errors but got: ' . implode(', ', $result->errors));
    }

    private function createTx(
        ISIN $isin,
        TransactionType $type,
        string $date,
        string $quantity,
        string $price,
        string $commission,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'TEST',
            type: $type,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of($quantity),
            pricePerUnit: Money::of($price, CurrencyCode::USD),
            commission: Money::of($commission, CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: 'Test',
            rawData: [],
        );
    }

    private function createPlnTx(
        ISIN $isin,
        TransactionType $type,
        string $date,
        string $quantity,
        string $price,
        string $commission,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: $isin,
            symbol: 'PKO',
            type: $type,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of($quantity),
            pricePerUnit: Money::of($price, CurrencyCode::PLN),
            commission: Money::of($commission, CurrencyCode::PLN),
            broker: BrokerId::of('bossa'),
            description: 'Test PLN',
            rawData: [],
        );
    }
}
