<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Exception\InsufficientSharesException;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class TaxPositionLedgerTest extends TestCase
{
    private TaxPositionLedger $ledger;

    protected function setUp(): void
    {
        $this->ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );
    }

    /**
     * Golden Dataset #1 — Tomasz example:
     * Buy 100 AAPL @ $170, commission $1, NBP 4.05 (14.03.2025)
     * Sell 100 AAPL @ $200, commission $1, NBP 3.95 (19.09.2025)
     *
     * Expected:
     *   Cost basis: 100 × 170 × 4.05 = 68 850.00
     *   Buy commission: 1 × 4.05 = 4.05
     *   Proceeds: 100 × 200 × 3.95 = 79 000.00
     *   Sell commission: 1 × 3.95 = 3.95
     *   Gain: 79 000 - 68 850 - 4.05 - 3.95 = 10 142.00
     */
    public function testGoldenDataset1SimpleBuySell(): void
    {
        $buyRate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');
        $sellRate = $this->nbpRate(CurrencyCode::USD, '3.95', '2025-09-19', '183/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
        );

        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
        );

        self::assertCount(1, $results);

        $closed = $results[0];
        self::assertTrue($closed->costBasisPLN->isEqualTo('68850.00'));
        self::assertTrue($closed->buyCommissionPLN->isEqualTo('4.05'));
        self::assertTrue($closed->proceedsPLN->isEqualTo('79000.00'));
        self::assertTrue($closed->sellCommissionPLN->isEqualTo('3.95'));
        self::assertTrue($closed->gainLossPLN->isEqualTo('10142.00'));
        self::assertTrue($closed->quantity->isEqualTo('100'));
    }

    /**
     * FIFO cross-broker:
     * Buy 100 AAPL on IBKR (Jan) @ $170
     * Buy 100 AAPL on Degiro (Mar) @ $180
     * Sell 50 AAPL on Degiro (Jun) @ $200
     * → FIFO: sold from IBKR (Jan buy), NOT from Degiro
     */
    public function testFifoMatchesOldestAcrossBrokers(): void
    {
        $rate1 = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');
        $rate2 = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');
        $rate3 = $this->nbpRate(CurrencyCode::USD, '3.90', '2025-06-19', '120/A/NBP/2025');

        $ibkrBuy = TransactionId::generate();
        $degiroBuy = TransactionId::generate();

        $this->ledger->registerBuy(
            $ibkrBuy,
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate1,
        );

        $this->ledger->registerBuy(
            $degiroBuy,
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('180.00', CurrencyCode::USD),
            Money::of('2.00', CurrencyCode::USD),
            BrokerId::of('degiro'),
            $rate2,
        );

        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('50'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('degiro'),
            $rate3,
        );

        // FIFO: sold 50 from IBKR January buy (oldest)
        self::assertCount(1, $results);
        self::assertTrue($results[0]->buyTransactionId->equals($ibkrBuy));
        self::assertTrue($results[0]->buyBroker->equals(BrokerId::of('ibkr')));
        self::assertTrue($results[0]->sellBroker->equals(BrokerId::of('degiro')));
        self::assertTrue($results[0]->quantity->isEqualTo('50'));

        // 50 remaining from IBKR + 100 from Degiro = 150 open
        self::assertCount(2, $this->ledger->openPositions());
    }

    /**
     * Partial sell: sell more than one buy lot
     * Buy 30 @ $100 (Jan)
     * Buy 70 @ $110 (Feb)
     * Sell 50 → matches: 30 from Jan + 20 from Feb
     */
    public function testPartialSellAcrossLots(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $buy1 = TransactionId::generate();
        $buy2 = TransactionId::generate();

        $this->ledger->registerBuy(
            $buy1,
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('30'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $this->ledger->registerBuy(
            $buy2,
            new \DateTimeImmutable('2025-02-15'),
            BigDecimal::of('70'),
            Money::of('110.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('50'),
            Money::of('150.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        // 2 closed positions: 30 from buy1 + 20 from buy2
        self::assertCount(2, $results);
        self::assertTrue($results[0]->buyTransactionId->equals($buy1));
        self::assertTrue($results[0]->quantity->isEqualTo('30'));
        self::assertTrue($results[1]->buyTransactionId->equals($buy2));
        self::assertTrue($results[1]->quantity->isEqualTo('20'));

        // 50 remaining from buy2
        $openPositions = $this->ledger->openPositions();
        self::assertCount(1, $openPositions);
        self::assertTrue($openPositions[0]->remainingQuantity()->isEqualTo('50'));
    }

    /**
     * Sell without buy → InsufficientSharesException
     */
    public function testSellWithoutBuyThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $this->expectException(InsufficientSharesException::class);

        $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );
    }

    /**
     * B-01 fix: commission allocation correctness on partial sells.
     * Buy 100 @ $100, commission $10
     * Sell 60 → commission allocated: $10 × (60/100) = $6
     * Sell 40 → commission allocated: $10 × (40/100) = $4
     * Total allocated commission = $10 (exact)
     */
    public function testCommissionAllocationOnPartialSells(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('100'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('10.00', CurrencyCode::USD), // $10 commission
            BrokerId::of('ibkr'),
            $rate,
        );

        // Sell 60
        $results1 = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('60'),
            Money::of('120.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        // Sell remaining 40
        $results2 = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-07-20'),
            BigDecimal::of('40'),
            Money::of('120.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        // Total buy commission allocated: should be exactly $10 × 4.00 = 40.00 PLN
        $totalBuyComm = $results1[0]->buyCommissionPLN->plus($results2[0]->buyCommissionPLN);
        self::assertTrue($totalBuyComm->isEqualTo('40.00'));

        // No open positions left
        self::assertCount(0, $this->ledger->openPositions());
    }

    /**
     * flushNewClosedPositions — returns and clears
     */
    public function testFlushNewClosedPositions(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('100'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('100'),
            Money::of('120.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $flushed = $this->ledger->flushNewClosedPositions();
        self::assertCount(1, $flushed);

        // Second flush returns empty
        $flushed2 = $this->ledger->flushNewClosedPositions();
        self::assertCount(0, $flushed2);
    }

    // --- Task 1: Guard clause tests (QA review C-01, C-02) ---

    /**
     * C-01: registerBuy with quantity = 0 must throw.
     * Zero quantity has no business meaning for a buy.
     */
    public function testRegisterBuyWithZeroQuantityThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::zero(), // zero quantity
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );
    }

    /**
     * C-01: registerBuy with negative quantity must throw.
     * Negative quantity is nonsensical for a buy.
     */
    public function testRegisterBuyWithNegativeQuantityThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('-10'), // negative quantity
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );
    }

    /**
     * C-02: registerSell with quantity = 0 must throw.
     */
    public function testRegisterSellWithZeroQuantityThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');

        // First register a buy so InsufficientSharesException is not the cause
        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::zero(), // zero quantity
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );
    }

    /**
     * C-02: registerBuy with negative price must throw.
     */
    public function testRegisterBuyWithNegativePriceThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price per unit cannot be negative');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('-170.00', CurrencyCode::USD), // negative price
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );
    }

    // --- Task 4: Fractional shares test (QA review C-05) ---

    /**
     * C-05: Fractional shares — buy 0.5 AAPL, sell 0.5 AAPL.
     * Verifies BigDecimal precision for sub-integer quantities.
     *
     * Buy 0.5 AAPL @ $170, commission $1, NBP 4.05
     * Sell 0.5 AAPL @ $200, commission $1, NBP 3.95
     *
     * Cost basis: 0.5 * 170 * 4.05 = 344.25
     * Buy commission: 1 * 4.05 = 4.05 (full commission, 1 lot)
     * Proceeds: 0.5 * 200 * 3.95 = 395.00
     * Sell commission: 1 * 3.95 = 3.95
     * Gain: 395.00 - 344.25 - 4.05 - 3.95 = 42.75
     */
    public function testFractionalSharesBuyAndSell(): void
    {
        $buyRate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');
        $sellRate = $this->nbpRate(CurrencyCode::USD, '3.95', '2025-09-19', '183/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('0.5'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
        );

        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('0.5'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
        );

        self::assertCount(1, $results);
        $closed = $results[0];

        // costBasis = 0.5 * 170 * 4.05 = 344.25
        self::assertTrue($closed->costBasisPLN->isEqualTo('344.25'));

        // buyCommission = 1 * 4.05 = 4.05
        self::assertTrue($closed->buyCommissionPLN->isEqualTo('4.05'));

        // proceeds = 0.5 * 200 * 3.95 = 395.00
        self::assertTrue($closed->proceedsPLN->isEqualTo('395.00'));

        // sellCommission = 1 * 3.95 = 3.95
        self::assertTrue($closed->sellCommissionPLN->isEqualTo('3.95'));

        // gain = 395.00 - 344.25 - 4.05 - 3.95 = 42.75
        self::assertTrue($closed->gainLossPLN->isEqualTo('42.75'));

        // No open positions remaining
        self::assertCount(0, $this->ledger->openPositions());
    }

    // --- Task 5: Zero gain test (QA review C-03) ---

    /**
     * C-03: Buy and sell at the same price with the same NBP rate.
     * Gain should be exactly -(buyCommission + sellCommission).
     *
     * Buy 100 @ $170, commission $1, NBP 4.00
     * Sell 100 @ $170, commission $1, NBP 4.00
     *
     * Cost basis: 100 * 170 * 4.00 = 68000.00
     * Buy commission: 1 * 4.00 = 4.00
     * Proceeds: 100 * 170 * 4.00 = 68000.00
     * Sell commission: 1 * 4.00 = 4.00
     * Gain: 68000 - 68000 - 4.00 - 4.00 = -8.00
     */
    public function testZeroGainWhenBuyAndSellAtSamePrice(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-03-14', '052/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        self::assertCount(1, $results);
        $closed = $results[0];

        // proceeds == costBasis, so gain = -(commissions)
        // buyComm = 1 * 4.00 = 4.00, sellComm = 1 * 4.00 = 4.00
        // gain = 68000 - 68000 - 4.00 - 4.00 = -8.00
        self::assertTrue($closed->costBasisPLN->isEqualTo('68000.00'));
        self::assertTrue($closed->proceedsPLN->isEqualTo('68000.00'));
        self::assertTrue($closed->buyCommissionPLN->isEqualTo('4.00'));
        self::assertTrue($closed->sellCommissionPLN->isEqualTo('4.00'));
        self::assertTrue($closed->gainLossPLN->isEqualTo('-8.00'));
    }

    // --- Task 6: Multiple sells same day (QA review C-04) ---

    /**
     * C-04: Two sells on the same date for the same instrument.
     * Both use the same NBP sell rate. FIFO should consume lots in order.
     *
     * Buy 100 AAPL @ $170, commission $1, NBP 4.05 (2025-03-15)
     * Sell 60 AAPL @ $200, commission $1, NBP 3.95 (2025-09-20)
     * Sell 40 AAPL @ $200, commission $1, NBP 3.95 (2025-09-20) — same day
     *
     * Sell 1: costBasis = 60 * 170 * 4.05 = 41310.00
     *         buyComm = (1*4.05) * (60/100) = 2.43
     *         proceeds = 60 * 200 * 3.95 = 47400.00
     *         sellComm = 1 * 3.95 = 3.95
     *         gain = 47400 - 41310 - 2.43 - 3.95 = 6083.62
     *
     * Sell 2: costBasis = 40 * 170 * 4.05 = 27540.00
     *         buyComm = (1*4.05) * (40/100) = 1.62
     *         proceeds = 40 * 200 * 3.95 = 31600.00
     *         sellComm = 1 * 3.95 = 3.95
     *         gain = 31600 - 27540 - 1.62 - 3.95 = 4054.43
     */
    public function testMultipleSellsSameDay(): void
    {
        $buyRate = $this->nbpRate(CurrencyCode::USD, '4.05', '2025-03-14', '052/A/NBP/2025');
        $sellRate = $this->nbpRate(CurrencyCode::USD, '3.95', '2025-09-19', '183/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
        );

        // First sell: 60 shares on 2025-09-20
        $results1 = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('60'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
        );

        // Second sell: 40 shares on same day 2025-09-20
        $results2 = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('40'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
        );

        self::assertCount(1, $results1);
        self::assertCount(1, $results2);

        $closed1 = $results1[0];
        $closed2 = $results2[0];

        // Both sells on the same date
        self::assertEquals(
            $closed1->sellDate->format('Y-m-d'),
            $closed2->sellDate->format('Y-m-d'),
        );

        // Sell 1: 60 shares
        // costBasis = 60 * (170 * 4.05) = 60 * 688.50 = 41310.00
        self::assertTrue($closed1->costBasisPLN->isEqualTo('41310.00'));
        self::assertTrue($closed1->quantity->isEqualTo('60'));
        self::assertTrue($closed1->proceedsPLN->isEqualTo('47400.00')); // 60 * 200 * 3.95

        // Sell 2: remaining 40 shares from same buy lot
        // costBasis = 40 * (170 * 4.05) = 40 * 688.50 = 27540.00
        self::assertTrue($closed2->costBasisPLN->isEqualTo('27540.00'));
        self::assertTrue($closed2->quantity->isEqualTo('40'));
        self::assertTrue($closed2->proceedsPLN->isEqualTo('31600.00')); // 40 * 200 * 3.95

        // All positions consumed
        self::assertCount(0, $this->ledger->openPositions());
    }

    // --- P0-009: registerSell atomicity — partial failure must not corrupt state ---

    /**
     * P0-009: Buy 50, sell 100 — InsufficientSharesException must be thrown
     * AND aggregate state must remain unchanged (atomic rollback).
     */
    public function testSellMoreThanAvailableIsAtomicRollback(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');
        $buyTx = TransactionId::generate();

        $this->ledger->registerBuy(
            $buyTx,
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('50'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        // Snapshot state before failed sell
        $openBefore = $this->ledger->openPositions();
        self::assertCount(1, $openBefore);
        $remainingBefore = $openBefore[0]->remainingQuantity();

        try {
            $this->ledger->registerSell(
                TransactionId::generate(),
                new \DateTimeImmutable('2025-06-20'),
                BigDecimal::of('100'), // more than 50 available
                Money::of('200.00', CurrencyCode::USD),
                Money::of('1.00', CurrencyCode::USD),
                BrokerId::of('ibkr'),
                $rate,
            );
            self::fail('InsufficientSharesException expected');
        } catch (InsufficientSharesException) {
            // Aggregate state must be unchanged
            $openAfter = $this->ledger->openPositions();
            self::assertCount(1, $openAfter, 'Open positions count must not change after failed sell');
            self::assertTrue(
                $openAfter[0]->remainingQuantity()->isEqualTo($remainingBefore),
                'Remaining quantity must not change after failed sell',
            );

            // No closed positions should have been created
            $flushed = $this->ledger->flushNewClosedPositions();
            self::assertCount(0, $flushed, 'No closed positions should be created on failed sell');
        }
    }

    // --- P0-002: OpenPosition.reduceQuantity guard on negative ---

    /**
     * P0-002: Buy 50, sell 50 — then verify no open position has negative remaining.
     * Also test that reduceQuantity with amount > remaining throws.
     */
    public function testReduceQuantityBeyondRemainingThrows(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('50'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $openPosition = $this->ledger->openPositions()[0];

        $this->expectException(\LogicException::class);
        $openPosition->reduceQuantity(BigDecimal::of('60')); // more than 50
    }

    /**
     * P1-048: Buy 50, sell 100 — partial consume before InsufficientSharesException.
     * After atomic rollback, aggregate must support subsequent valid operations.
     */
    public function testAggregateUsableAfterFailedSell(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');

        $this->ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('50'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        // This should fail
        try {
            $this->ledger->registerSell(
                TransactionId::generate(),
                new \DateTimeImmutable('2025-06-20'),
                BigDecimal::of('100'),
                Money::of('200.00', CurrencyCode::USD),
                Money::of('1.00', CurrencyCode::USD),
                BrokerId::of('ibkr'),
                $rate,
            );
        } catch (InsufficientSharesException) {
            // expected
        }

        // This should succeed — aggregate is still usable
        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('50'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        self::assertCount(1, $results);
        self::assertTrue($results[0]->quantity->isEqualTo('50'));
        self::assertCount(0, $this->ledger->openPositions());
    }

    /**
     * P1-045: Two buys on the same date from different brokers — FIFO must be deterministic.
     */
    public function testSameDateBuyOrderIsDeterministic(): void
    {
        $rate = $this->nbpRate(CurrencyCode::USD, '4.00', '2025-01-14', '009/A/NBP/2025');
        $tx1 = TransactionId::generate();
        $tx2 = TransactionId::generate();

        // Register buys on same date, different brokers
        $this->ledger->registerBuy(
            $tx1,
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('50'),
            Money::of('100.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $this->ledger->registerBuy(
            $tx2,
            new \DateTimeImmutable('2025-01-15'), // same date
            BigDecimal::of('50'),
            Money::of('110.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('degiro'),
            $rate,
        );

        // Sell 50 — must always match the same lot (deterministic)
        $results = $this->ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('50'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
        );

        $firstMatchTx = $results[0]->buyTransactionId;

        // Run 10 times to verify determinism (same aggregate, same data)
        // (In practice, the sort is stable if we add secondary sort key)
        for ($i = 0; $i < 10; $i++) {
            $ledger2 = TaxPositionLedger::create(
                UserId::generate(),
                ISIN::fromString('US0378331005'),
                TaxCategory::EQUITY,
            );

            $ledger2->registerBuy($tx1, new \DateTimeImmutable('2025-01-15'), BigDecimal::of('50'), Money::of('100.00', CurrencyCode::USD), Money::of('1.00', CurrencyCode::USD), BrokerId::of('ibkr'), $rate);
            $ledger2->registerBuy($tx2, new \DateTimeImmutable('2025-01-15'), BigDecimal::of('50'), Money::of('110.00', CurrencyCode::USD), Money::of('1.00', CurrencyCode::USD), BrokerId::of('degiro'), $rate);

            $r = $ledger2->registerSell(TransactionId::generate(), new \DateTimeImmutable('2025-06-20'), BigDecimal::of('50'), Money::of('200.00', CurrencyCode::USD), Money::of('1.00', CurrencyCode::USD), BrokerId::of('ibkr'), $rate);

            self::assertTrue(
                $r[0]->buyTransactionId->equals($firstMatchTx),
                "FIFO matching must be deterministic for same-date buys (iteration {$i})",
            );
        }
    }

    private function nbpRate(CurrencyCode $currency, string $rate, string $date, string $table): NBPRate
    {
        return NBPRate::create($currency, BigDecimal::of($rate), new \DateTimeImmutable($date), $table);
    }
}
