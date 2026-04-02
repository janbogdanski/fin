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
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\CurrencyConverterInterface;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for FIFO matching (P2-009).
 *
 * Property: For any sequence of buys and sells where total_sells <= total_buys,
 * the sum of closed position quantities equals total sells.
 */
final class FIFOPropertyTest extends TestCase
{
    private CurrencyConverterInterface $converter;

    protected function setUp(): void
    {
        $this->converter = new CurrencyConverter();
    }

    /**
     * Property: sum(closed_position.quantity) == total_sell_quantity
     *
     * For any random sequence of buys/sells where sells never exceed cumulative buys,
     * every sold share must appear in exactly one ClosedPosition.
     */
    public function testClosedPositionQuantitySumEqualsTotalSells(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $this->runRandomBuySellSequence($i);
        }
    }

    /**
     * Property: after all sells, remaining open positions =
     * total_buys - total_sells.
     */
    public function testOpenPositionRemainingEqualsUnsoldShares(): void
    {
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $this->runAndCheckRemainingPositions($i);
        }
    }

    /**
     * Property: FIFO order — closed positions reference buy dates
     * in non-decreasing order per sell transaction.
     */
    public function testFIFOOrderIsRespected(): void
    {
        $ledger = $this->createLedger();
        $broker = BrokerId::of('test');
        $rate = $this->makeRate();

        // Register 3 buys on different dates
        $dates = [
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-02-01'),
            new \DateTimeImmutable('2025-03-01'),
        ];

        foreach ($dates as $date) {
            $ledger->registerBuy(
                TransactionId::generate(),
                $date,
                BigDecimal::of('10'),
                Money::of('100', CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
        }

        // Sell 25 shares — should consume all of buy #1 (10) + all of buy #2 (10) + 5 from buy #3
        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-04-01'),
            BigDecimal::of('25'),
            Money::of('150', CurrencyCode::USD),
            Money::of('0', CurrencyCode::USD),
            $broker,
            $rate,
            $this->converter,
        );

        self::assertCount(3, $closed);
        self::assertSame('2025-01-01', $closed[0]->buyDate->format('Y-m-d'));
        self::assertSame('2025-02-01', $closed[1]->buyDate->format('Y-m-d'));
        self::assertSame('2025-03-01', $closed[2]->buyDate->format('Y-m-d'));
        self::assertTrue($closed[0]->quantity->isEqualTo('10'));
        self::assertTrue($closed[1]->quantity->isEqualTo('10'));
        self::assertTrue($closed[2]->quantity->isEqualTo('5'));
    }

    private function runRandomBuySellSequence(int $seed): void
    {
        mt_srand($seed);

        $ledger = $this->createLedger();
        $broker = BrokerId::of('test');
        $rate = $this->makeRate();

        $totalBought = BigDecimal::zero();
        $totalSold = BigDecimal::zero();
        $allClosed = [];

        // Generate 3-8 buys
        $numBuys = mt_rand(3, 8);
        $baseDate = new \DateTimeImmutable('2025-01-01');

        for ($b = 0; $b < $numBuys; $b++) {
            $qty = BigDecimal::of((string) mt_rand(1, 100));
            $price = (string) (mt_rand(100, 50000) / 100);

            $ledger->registerBuy(
                TransactionId::generate(),
                $baseDate->modify("+{$b} days"),
                $qty,
                Money::of($price, CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );

            $totalBought = $totalBought->plus($qty);
        }

        // Generate 1-4 sells, never exceeding available
        $numSells = mt_rand(1, min(4, $numBuys));
        $available = $totalBought;

        for ($s = 0; $s < $numSells; $s++) {
            if ($available->isZero()) {
                break;
            }

            $maxSell = min((int) $available->toScale(0)->__toString(), 100);

            if ($maxSell < 1) {
                break;
            }

            $qty = BigDecimal::of((string) mt_rand(1, $maxSell));
            $price = (string) (mt_rand(100, 50000) / 100);

            $closed = $ledger->registerSell(
                TransactionId::generate(),
                $baseDate->modify('+' . ($numBuys + $s) . ' days'),
                $qty,
                Money::of($price, CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );

            $allClosed = array_merge($allClosed, $closed);
            $totalSold = $totalSold->plus($qty);
            $available = $available->minus($qty);
        }

        // Property: sum of closed position quantities == total sold
        $closedQtySum = BigDecimal::zero();

        foreach ($allClosed as $cp) {
            $closedQtySum = $closedQtySum->plus($cp->quantity);
        }

        self::assertTrue(
            $closedQtySum->isEqualTo($totalSold),
            "Seed {$seed}: sum(closed.qty)={$closedQtySum} != totalSold={$totalSold}",
        );
    }

    private function runAndCheckRemainingPositions(int $seed): void
    {
        mt_srand($seed + 1000);

        $ledger = $this->createLedger();
        $broker = BrokerId::of('test');
        $rate = $this->makeRate();

        $totalBought = BigDecimal::zero();
        $totalSold = BigDecimal::zero();
        $baseDate = new \DateTimeImmutable('2025-01-01');

        $numBuys = mt_rand(2, 6);

        for ($b = 0; $b < $numBuys; $b++) {
            $qty = BigDecimal::of((string) mt_rand(5, 50));
            $ledger->registerBuy(
                TransactionId::generate(),
                $baseDate->modify("+{$b} days"),
                $qty,
                Money::of('100', CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
            $totalBought = $totalBought->plus($qty);
        }

        $available = $totalBought;
        $numSells = mt_rand(1, min(3, $numBuys));

        for ($s = 0; $s < $numSells; $s++) {
            if ($available->isZero()) {
                break;
            }

            $maxSell = min((int) $available->toScale(0)->__toString(), 30);

            if ($maxSell < 1) {
                break;
            }

            $qty = BigDecimal::of((string) mt_rand(1, $maxSell));
            $ledger->registerSell(
                TransactionId::generate(),
                $baseDate->modify('+' . ($numBuys + $s) . ' days'),
                $qty,
                Money::of('150', CurrencyCode::USD),
                Money::of('0', CurrencyCode::USD),
                $broker,
                $rate,
                $this->converter,
            );
            $totalSold = $totalSold->plus($qty);
            $available = $available->minus($qty);
        }

        // Property: remaining open positions sum == totalBought - totalSold
        $remainingSum = BigDecimal::zero();

        foreach ($ledger->openPositions() as $pos) {
            $remainingSum = $remainingSum->plus($pos->remainingQuantity());
        }

        $expected = $totalBought->minus($totalSold);
        self::assertTrue(
            $remainingSum->isEqualTo($expected),
            "Seed {$seed}: remaining={$remainingSum} != expected={$expected}",
        );
    }

    private function createLedger(): TaxPositionLedger
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
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-01-01'),
            '001/A/NBP/2025',
        );
    }
}
