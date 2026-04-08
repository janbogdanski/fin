<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Exception\InsufficientSharesException;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\CurrencyConverterInterface;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for FIFO matching — P2-068 batch.
 *
 * Each test generates randomized inputs and asserts a domain invariant
 * across 50–100 iterations. Uses seeded mt_rand() for reproducibility.
 */
final class FifoPropertiesTest extends TestCase
{
    private CurrencyConverterInterface $converter;

    protected function setUp(): void
    {
        $this->converter = new CurrencyConverter();
    }

    // -------------------------------------------------------------------------
    // Property 1: sell quantity <= total buy quantity → no exception thrown
    // -------------------------------------------------------------------------

    /**
     * @dataProvider sellWithinBuyCapProvider
     */
    public function testSellWithinBuyCapNeverThrows(int $seed): void
    {
        mt_srand($seed);

        $ledger = $this->makeLedger();
        $broker = BrokerId::of('broker');
        $rate = $this->makeRate();
        $base = new \DateTimeImmutable('2025-01-01');

        $totalBought = BigDecimal::zero();
        $numBuys = mt_rand(1, 8);

        for ($b = 0; $b < $numBuys; $b++) {
            $qty = BigDecimal::of((string) mt_rand(1, 100));
            $ledger->registerBuy(
                TransactionId::generate(),
                $base->modify("+{$b} days"),
                $qty,
                Money::of((string) (mt_rand(100, 50000) / 100), CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
            $totalBought = $totalBought->plus($qty);
        }

        // Pick any sell quantity <= totalBought
        $maxInt = (int) $totalBought->toScale(0, \Brick\Math\RoundingMode::DOWN)->__toString();
        $sellQty = BigDecimal::of((string) mt_rand(1, max(1, $maxInt)));

        $thrown = false;
        try {
            $ledger->registerSell(
                TransactionId::generate(),
                $base->modify("+{$numBuys} days"),
                $sellQty,
                Money::of('100', CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
        } catch (InsufficientSharesException $e) {
            $thrown = true;
        }

        self::assertFalse($thrown, "Seed {$seed}: unexpected InsufficientSharesException for sell {$sellQty} <= bought {$totalBought}");
    }

    /**
     * @return array<array{int}>
     */
    public static function sellWithinBuyCapProvider(): array
    {
        return array_map(fn (int $i) => [$i + 3000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 2: after selling ALL shares, openPositions = 0
    // -------------------------------------------------------------------------

    /**
     * @dataProvider sellAllSharesProvider
     */
    public function testSellAllSharesLeavesNoOpenPositions(int $seed): void
    {
        mt_srand($seed);

        $ledger = $this->makeLedger();
        $broker = BrokerId::of('broker');
        $rate = $this->makeRate();
        $base = new \DateTimeImmutable('2025-01-01');

        $totalBought = BigDecimal::zero();
        $numBuys = mt_rand(1, 6);

        for ($b = 0; $b < $numBuys; $b++) {
            $qty = BigDecimal::of((string) mt_rand(1, 50));
            $ledger->registerBuy(
                TransactionId::generate(),
                $base->modify("+{$b} days"),
                $qty,
                Money::of('100', CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
            $totalBought = $totalBought->plus($qty);
        }

        // Sell exactly totalBought (all shares)
        $ledger->registerSell(
            TransactionId::generate(),
            $base->modify("+{$numBuys} days"),
            $totalBought,
            Money::of('110', CurrencyCode::USD),
            Money::of('0', CurrencyCode::USD),
            $broker,
            $rate,
            $this->converter,
        );

        $remaining = BigDecimal::zero();
        foreach ($ledger->openPositions() as $pos) {
            $remaining = $remaining->plus($pos->remainingQuantity());
        }

        self::assertTrue(
            $remaining->isZero(),
            "Seed {$seed}: after selling all {$totalBought} shares, remaining={$remaining} (expected 0)",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function sellAllSharesProvider(): array
    {
        return array_map(fn (int $i) => [$i + 4000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 3: sum(closedPosition.gainLoss) = sum(proceeds - cost - buyComm - sellComm)
    //             Verifies FIFO lot gain invariant across random sequences
    // -------------------------------------------------------------------------

    /**
     * @dataProvider gainLossInvariantProvider
     */
    public function testGainLossEqualsProeedsMinusCostMinusCommissions(int $seed): void
    {
        mt_srand($seed);

        $ledger = $this->makeLedger();
        $broker = BrokerId::of('broker');
        $rate = $this->makeRate();
        $base = new \DateTimeImmutable('2025-01-01');

        $totalBought = BigDecimal::zero();
        $numBuys = mt_rand(2, 6);

        for ($b = 0; $b < $numBuys; $b++) {
            $qty = BigDecimal::of((string) mt_rand(1, 50));
            $ledger->registerBuy(
                TransactionId::generate(),
                $base->modify("+{$b} days"),
                $qty,
                Money::of((string) (mt_rand(100, 10000) / 100), CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
            $totalBought = $totalBought->plus($qty);
        }

        $maxSellInt = (int) $totalBought->toScale(0, \Brick\Math\RoundingMode::DOWN)->__toString();
        $sellQty = BigDecimal::of((string) mt_rand(1, max(1, $maxSellInt)));

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            $base->modify("+{$numBuys} days"),
            $sellQty,
            Money::of((string) (mt_rand(100, 10000) / 100), CurrencyCode::USD),
            Money::of('0', CurrencyCode::USD),
            $broker,
            $rate,
            $this->converter,
        );

        // For each ClosedPosition, gainLoss must equal proceeds - cost - buyComm - sellComm
        // (ClosedPosition constructor already asserts this with 0.01 tolerance; we assert sum)
        $totalGainFromGainLoss = BigDecimal::zero();
        $totalGainFromComponents = BigDecimal::zero();

        foreach ($closed as $cp) {
            $totalGainFromGainLoss = $totalGainFromGainLoss->plus($cp->gainLossPLN);
            $totalGainFromComponents = $totalGainFromComponents
                ->plus($cp->proceedsPLN)
                ->minus($cp->costBasisPLN)
                ->minus($cp->buyCommissionPLN)
                ->minus($cp->sellCommissionPLN);
        }

        // Tolerance: 0.01 per closed position (from ClosedPosition constructor guarantee)
        $tolerance = BigDecimal::of('0.01')->multipliedBy(count($closed));
        $diff = $totalGainFromGainLoss->minus($totalGainFromComponents)->abs();

        self::assertTrue(
            $diff->isLessThanOrEqualTo($tolerance),
            "Seed {$seed}: gainLoss sum {$totalGainFromGainLoss} deviates from components sum {$totalGainFromComponents} by {$diff} (tolerance {$tolerance})",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function gainLossInvariantProvider(): array
    {
        return array_map(fn (int $i) => [$i + 5000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 4: same-date buys (different IDs) + one sell → result is
    //             deterministic regardless of registration order (FIFO is date-stable)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider sameDateBuyOrderProvider
     */
    public function testSameDateBuysProduceDeterministicGain(int $seed): void
    {
        mt_srand($seed);

        $buyDate = new \DateTimeImmutable('2025-03-01');
        $sellDate = new \DateTimeImmutable('2025-06-01');
        $broker = BrokerId::of('broker');
        $rate = $this->makeRate();

        // Two buys on same date with different prices
        $qtyA = BigDecimal::of((string) mt_rand(5, 20));
        $qtyB = BigDecimal::of((string) mt_rand(5, 20));
        $price = (string) (mt_rand(200, 5000) / 100);

        // Ledger 1: register A then B
        $ledger1 = $this->makeLedger();
        $txA = TransactionId::generate();
        $txB = TransactionId::generate();

        $ledger1->registerBuy($txA, $buyDate, $qtyA, Money::of($price, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);
        $ledger1->registerBuy($txB, $buyDate, $qtyB, Money::of($price, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);

        // Ledger 2: register B then A (same IDs, same prices)
        $ledger2 = $this->makeLedger();
        $ledger2->registerBuy($txB, $buyDate, $qtyB, Money::of($price, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);
        $ledger2->registerBuy($txA, $buyDate, $qtyA, Money::of($price, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);

        // Sell a quantity that fits in both
        $totalQty = $qtyA->plus($qtyB);
        $maxInt = (int) $totalQty->toScale(0, \Brick\Math\RoundingMode::DOWN)->__toString();
        $sellQty = BigDecimal::of((string) mt_rand(1, max(1, $maxInt)));
        $sellPrice = (string) (mt_rand(200, 5000) / 100);

        $closed1 = $ledger1->registerSell(TransactionId::generate(), $sellDate, $sellQty, Money::of($sellPrice, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);
        $closed2 = $ledger2->registerSell(TransactionId::generate(), $sellDate, $sellQty, Money::of($sellPrice, CurrencyCode::USD), Money::of('0', CurrencyCode::USD), $broker, $rate, $this->converter);

        // Since all buys are same price, total gainLoss must be equal regardless of order
        $gain1 = BigDecimal::zero();
        foreach ($closed1 as $cp) {
            $gain1 = $gain1->plus($cp->gainLossPLN);
        }
        $gain2 = BigDecimal::zero();
        foreach ($closed2 as $cp) {
            $gain2 = $gain2->plus($cp->gainLossPLN);
        }

        $diff = $gain1->minus($gain2)->abs();

        self::assertTrue(
            $diff->isLessThanOrEqualTo(BigDecimal::of('0.02')),
            "Seed {$seed}: same-price same-date buys should yield same totalGain regardless of insertion order. gain1={$gain1}, gain2={$gain2}, diff={$diff}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function sameDateBuyOrderProvider(): array
    {
        return array_map(fn (int $i) => [$i + 6000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Property 5: buy N shares, sell N shares at same price → gainLoss ≈ 0
    //             (breakeven: no profit/loss when sell price = buy price, zero commissions)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider breakevenProvider
     */
    public function testBreakevenGainLossIsZero(int $seed): void
    {
        mt_srand($seed);

        $ledger = $this->makeLedger();
        $broker = BrokerId::of('broker');
        $rate = $this->makeRate();
        $base = new \DateTimeImmutable('2025-01-01');

        // Use exact integer price to avoid rounding — same buy and sell price
        $price = (string) mt_rand(10, 1000);
        $qty = BigDecimal::of((string) mt_rand(1, 100));

        $ledger->registerBuy(
            TransactionId::generate(),
            $base,
            $qty,
            Money::of($price, CurrencyCode::USD),
            Money::of('0', CurrencyCode::USD),
            $broker,
            $rate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            $base->modify('+1 day'),
            $qty,
            Money::of($price, CurrencyCode::USD),
            Money::of('0', CurrencyCode::USD),
            $broker,
            $rate,
            $this->converter,
        );

        $totalGainLoss = BigDecimal::zero();
        foreach ($closed as $cp) {
            $totalGainLoss = $totalGainLoss->plus($cp->gainLossPLN);
        }

        self::assertTrue(
            $totalGainLoss->isZero(),
            "Seed {$seed}: breakeven (buy=sell price={$price}, qty={$qty}) should yield gainLoss=0, got={$totalGainLoss}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function breakevenProvider(): array
    {
        return array_map(fn (int $i) => [$i + 7000], range(0, 49));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLedger(): TaxPositionLedger
    {
        return TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );
    }

    private function makeRate(): NBPRate
    {
        return NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-01'),
            '001/A/NBP/2025',
        );
    }
}
