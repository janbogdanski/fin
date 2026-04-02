<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\CurrencyMismatchException;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCreatesMoneyWithFullPrecision(): void
    {
        $money = Money::of('170.123456', CurrencyCode::USD);

        self::assertTrue($money->amount()->isEqualTo('170.123456'));
        self::assertTrue($money->currency()->equals(CurrencyCode::USD));
    }

    public function testRoundedReturnsScale2(): void
    {
        $money = Money::of('170.126', CurrencyCode::USD);
        $rounded = $money->rounded();
        self::assertTrue($rounded->amount()->isEqualTo('170.13'));
    }

    public function testZero(): void
    {
        $zero = Money::zero(CurrencyCode::PLN);
        self::assertTrue($zero->amount()->isZero());
        self::assertTrue($zero->currency()->equals(CurrencyCode::PLN));
    }

    public function testAddSameCurrency(): void
    {
        $a = Money::of('100.50', CurrencyCode::PLN);
        $b = Money::of('200.30', CurrencyCode::PLN);
        $result = $a->add($b);
        self::assertTrue($result->amount()->isEqualTo('300.80'));
    }

    public function testAddDifferentCurrencyThrows(): void
    {
        $pln = Money::of('100', CurrencyCode::PLN);
        $usd = Money::of('50', CurrencyCode::USD);
        $this->expectException(CurrencyMismatchException::class);
        $pln->add($usd);
    }

    public function testSubtract(): void
    {
        $a = Money::of('300.80', CurrencyCode::PLN);
        $b = Money::of('100.50', CurrencyCode::PLN);
        $result = $a->subtract($b);
        self::assertTrue($result->amount()->isEqualTo('200.30'));
    }

    public function testMultiply(): void
    {
        $price = Money::of('170.00', CurrencyCode::USD);
        $total = $price->multiply('100');
        self::assertTrue($total->amount()->isEqualTo('17000.00'));
    }

    public function testToPLNWithMatchingCurrency(): void
    {
        $usd = Money::of('170.00', CurrencyCode::USD);
        $rate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025');

        $pln = (new CurrencyConverter())->toPLN($usd, $rate);

        self::assertTrue($pln->amount()->isEqualTo('688.50'));
        self::assertTrue($pln->currency()->equals(CurrencyCode::PLN));
    }

    public function testToPLNCurrencyMismatchThrows(): void
    {
        $usd = Money::of('170.00', CurrencyCode::USD);
        $eurRate = NBPRate::create(CurrencyCode::EUR, BigDecimal::of('4.30'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025');

        $this->expectException(CurrencyMismatchException::class);
        (new CurrencyConverter())->toPLN($usd, $eurRate);
    }

    public function testToPLNAlreadyPlnReturnsSelf(): void
    {
        $pln = Money::of('1000.00', CurrencyCode::PLN);
        $anyRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025');

        $result = (new CurrencyConverter())->toPLN($pln, $anyRate);
        self::assertTrue($result->amount()->isEqualTo('1000.00'));
    }

    public function testIsNegative(): void
    {
        $loss = Money::of('-500.00', CurrencyCode::PLN);
        self::assertTrue($loss->isNegative());
    }

    /**
     * P3-004: Extreme large amount — 10M PLN.
     * Verifies BigDecimal handles large values without overflow.
     */
    public function testExtremeLargeAmountTenMillionPLN(): void
    {
        $money = Money::of('10000000.00', CurrencyCode::PLN);

        self::assertTrue($money->amount()->isEqualTo('10000000.00'));
        self::assertFalse($money->isZero());
        self::assertFalse($money->isNegative());

        $doubled = $money->add(Money::of('10000000.00', CurrencyCode::PLN));
        self::assertTrue($doubled->amount()->isEqualTo('20000000.00'));
    }

    /**
     * P3-005: Extreme small amount — 0.01 PLN (1 grosz).
     * Verifies precision at the minimum meaningful value.
     */
    public function testExtremeSmallAmountOneGroszPLN(): void
    {
        $money = Money::of('0.01', CurrencyCode::PLN);

        self::assertTrue($money->amount()->isEqualTo('0.01'));
        self::assertFalse($money->isZero());
        self::assertFalse($money->isNegative());

        $subtracted = $money->subtract(Money::of('0.01', CurrencyCode::PLN));
        self::assertTrue($subtracted->isZero());
    }

    /**
     * Golden Dataset #1 — Tomasz example:
     * Buy 100 AAPL @ $170, commission $1, NBP 4.05
     * Sell 100 AAPL @ $200, commission $1, NBP 3.95
     * Expected gain: 10 142.00 PLN
     */
    public function testGoldenDataset1FullCalculation(): void
    {
        $buyRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.05'), new \DateTimeImmutable('2025-03-14'), '052/A/NBP/2025');
        $sellRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('3.95'), new \DateTimeImmutable('2025-09-19'), '183/A/NBP/2025');

        $buyCost = (new CurrencyConverter())->toPLN(Money::of('170.00', CurrencyCode::USD)->multiply('100'), $buyRate);
        self::assertTrue($buyCost->rounded()->amount()->isEqualTo('68850.00'));

        $buyComm = (new CurrencyConverter())->toPLN(Money::of('1.00', CurrencyCode::USD), $buyRate);
        self::assertTrue($buyComm->rounded()->amount()->isEqualTo('4.05'));

        $proceeds = (new CurrencyConverter())->toPLN(Money::of('200.00', CurrencyCode::USD)->multiply('100'), $sellRate);
        self::assertTrue($proceeds->rounded()->amount()->isEqualTo('79000.00'));

        $sellComm = (new CurrencyConverter())->toPLN(Money::of('1.00', CurrencyCode::USD), $sellRate);
        self::assertTrue($sellComm->rounded()->amount()->isEqualTo('3.95'));

        $gain = $proceeds->amount()
            ->minus($buyCost->amount())
            ->minus($buyComm->amount())
            ->minus($sellComm->amount());

        self::assertTrue($gain->isEqualTo('10142.00'));
    }
}
