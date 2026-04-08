<?php

declare(strict_types=1);

namespace App\Tests\Property;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for the Money value object — P2-068 batch.
 *
 * Properties are mathematical invariants that must hold for all valid inputs.
 * Inputs are generated with seeded mt_rand() for reproducibility.
 */
final class MoneyPropertiesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Property 1: add() is commutative — a + b = b + a
    // -------------------------------------------------------------------------

    #[DataProvider('commutativityProvider')]
    public function testAddIsCommutative(int $seed): void
    {
        mt_srand($seed);

        $currency = CurrencyCode::PLN;

        $amountA = $this->randomDecimalString();
        $amountB = $this->randomDecimalString();

        $a = Money::of($amountA, $currency);
        $b = Money::of($amountB, $currency);

        $aPlusB = $a->add($b);
        $bPlusA = $b->add($a);

        self::assertTrue(
            $aPlusB->amount()->isEqualTo($bPlusA->amount()),
            "Seed {$seed}: a+b={$aPlusB->amount()} != b+a={$bPlusA->amount()} for a={$amountA}, b={$amountB}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function commutativityProvider(): array
    {
        return array_map(fn (int $i) => [$i + 8000], range(0, 99));
    }

    // -------------------------------------------------------------------------
    // Property 2: multiply(n) is equivalent to n successive adds
    //             For integer n in [2, 10]: a.multiply(n) = a + a + ... (n times)
    // -------------------------------------------------------------------------

    #[DataProvider('multiplyEquivAddsProvider')]
    public function testMultiplyIsEquivalentToRepeatedAdd(int $seed): void
    {
        mt_srand($seed);

        $currency = CurrencyCode::EUR;
        $amount = $this->randomDecimalString();
        $n = mt_rand(2, 10);

        $a = Money::of($amount, $currency);
        $multiplied = $a->multiply((string) $n);

        // Sum n copies via add()
        $summed = Money::zero($currency);
        for ($i = 0; $i < $n; $i++) {
            $summed = $summed->add($a);
        }

        self::assertTrue(
            $multiplied->amount()->isEqualTo($summed->amount()),
            "Seed {$seed}: multiply({$n}) = {$multiplied->amount()} != sum of {$n} adds = {$summed->amount()} for amount={$amount}",
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function multiplyEquivAddsProvider(): array
    {
        return array_map(fn (int $i) => [$i + 9000], range(0, 99));
    }

    // -------------------------------------------------------------------------
    // Property 3: ratio allocation sums to original (no money lost in rounding)
    //
    // Money has no allocate() method, so we implement proportional split inline:
    // given total T and ratios [r1, r2, ..., rk] (sum = 1), each part is
    // T * ri rounded DOWN (scale 2), and the remainder is assigned to the last part.
    // Invariant: sum(parts) = T.rounded()
    // -------------------------------------------------------------------------

    #[DataProvider('allocationProvider')]
    public function testAllocationSumsToOriginal(int $seed): void
    {
        mt_srand($seed);

        $currency = CurrencyCode::USD;
        $total = Money::of($this->randomDecimalString(), $currency);
        $rounded = $total->rounded();

        // Generate 2–5 integer ratio parts
        $numParts = mt_rand(2, 5);
        $parts = [];
        $ratioSum = 0;

        for ($i = 0; $i < $numParts - 1; $i++) {
            $r = mt_rand(1, 10);
            $parts[] = $r;
            $ratioSum += $r;
        }
        $parts[] = mt_rand(1, 10);
        $ratioSum += end($parts);

        // Allocate: each share = floor(total * ratio / ratioSum, scale 2)
        $totalAmount = $rounded->amount();
        $allocated = [];
        $allocatedSum = BigDecimal::zero();

        for ($i = 0; $i < $numParts - 1; $i++) {
            $share = $totalAmount
                ->multipliedBy((string) $parts[$i])
                ->dividedBy((string) $ratioSum, 2, RoundingMode::DOWN);
            $allocated[] = $share;
            $allocatedSum = $allocatedSum->plus($share);
        }

        // Last part gets the remainder to ensure sum = total
        $remainder = $totalAmount->minus($allocatedSum)->toScale(2, RoundingMode::DOWN);
        $allocated[] = $remainder;
        $allocatedSum = $allocatedSum->plus($remainder);

        self::assertTrue(
            $allocatedSum->isEqualTo($totalAmount),
            "Seed {$seed}: allocation sum {$allocatedSum} != total {$totalAmount} (ratios=" . implode(':', $parts) . ')',
        );
    }

    /**
     * @return array<array{int}>
     */
    public static function allocationProvider(): array
    {
        return array_map(fn (int $i) => [$i + 10000], range(0, 99));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a random decimal string with 2 decimal places in range [1.00, 9999.99].
     */
    private function randomDecimalString(): string
    {
        $cents = mt_rand(100, 999999);

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
