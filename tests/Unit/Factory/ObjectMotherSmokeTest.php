<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\Tests\Factory\ClosedPositionMother;
use App\Tests\Factory\MoneyMother;
use App\Tests\Factory\NBPRateMother;
use App\Tests\Factory\NormalizedTransactionMother;
use App\Tests\Factory\UserMother;
use PHPUnit\Framework\TestCase;

/**
 * Ensures all ObjectMother factory methods produce valid domain objects.
 * Guards against drift between Mothers and domain model constructors.
 */
final class ObjectMotherSmokeTest extends TestCase
{
    public function testUserMotherStandard(): void
    {
        $user = UserMother::standard();
        self::assertInstanceOf(User::class, $user);
        self::assertNotEmpty($user->email());
    }

    public function testUserMotherWithProfile(): void
    {
        $user = UserMother::withProfile();
        self::assertInstanceOf(User::class, $user);
    }

    public function testUserMotherWithoutNIP(): void
    {
        $user = UserMother::withoutNIP();
        self::assertInstanceOf(User::class, $user);
    }

    public function testMoneyMotherUsd(): void
    {
        $money = MoneyMother::usd('100.00');
        self::assertInstanceOf(Money::class, $money);
    }

    public function testMoneyMotherPln(): void
    {
        $money = MoneyMother::pln('100.00');
        self::assertInstanceOf(Money::class, $money);
    }

    public function testMoneyMotherEur(): void
    {
        $money = MoneyMother::eur('100.00');
        self::assertInstanceOf(Money::class, $money);
    }

    public function testMoneyMotherZero(): void
    {
        $money = MoneyMother::zero();
        self::assertInstanceOf(Money::class, $money);
    }

    public function testNBPRateMotherUsd405(): void
    {
        $rate = NBPRateMother::usd405();
        self::assertInstanceOf(NBPRate::class, $rate);
    }

    public function testNBPRateMotherEur460(): void
    {
        $rate = NBPRateMother::eur460();
        self::assertInstanceOf(NBPRate::class, $rate);
    }

    public function testNormalizedTransactionMotherBuyAAPL(): void
    {
        $tx = NormalizedTransactionMother::buyAAPL();
        self::assertInstanceOf(NormalizedTransaction::class, $tx);
        self::assertSame('AAPL', $tx->symbol);
    }

    public function testNormalizedTransactionMotherSellAAPL(): void
    {
        $tx = NormalizedTransactionMother::sellAAPL();
        self::assertInstanceOf(NormalizedTransaction::class, $tx);
    }

    public function testNormalizedTransactionMotherDividendMSFT(): void
    {
        $tx = NormalizedTransactionMother::dividendMSFT();
        self::assertInstanceOf(NormalizedTransaction::class, $tx);
    }

    public function testClosedPositionMotherStandard(): void
    {
        $cp = ClosedPositionMother::standard();
        self::assertInstanceOf(ClosedPosition::class, $cp);
    }

    public function testClosedPositionMotherWithGain(): void
    {
        $cp = ClosedPositionMother::withGain('200.00');
        self::assertInstanceOf(ClosedPosition::class, $cp);
        self::assertTrue($cp->gainLossPLN->isPositive());
    }

    public function testClosedPositionMotherWithLoss(): void
    {
        $cp = ClosedPositionMother::withLoss('150.00');
        self::assertInstanceOf(ClosedPosition::class, $cp);
        self::assertTrue($cp->gainLossPLN->isNegative());
    }
}
