<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class NBPRateTest extends TestCase
{
    public function testCreatesValidRate(): void
    {
        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.0512'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );

        self::assertTrue($rate->currency()->equals(CurrencyCode::USD));
        self::assertTrue($rate->rate()->isEqualTo('4.0512'));
        self::assertSame('052/A/NBP/2025', $rate->tableNumber());
    }

    public function testRejectsZeroRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::zero(),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
    }

    public function testRejectsNegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('-1.5'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
    }

    public function testRejectsInvalidTableNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-03-14'),
            'INVALID',
        );
    }

    public function testAcceptsTableTypesABC(): void
    {
        foreach (['052/A/NBP/2025', '012/B/NBP/2025', '001/C/NBP/2025'] as $table) {
            $rate = NBPRate::create(
                CurrencyCode::USD,
                BigDecimal::of('4.05'),
                new \DateTimeImmutable('2025-03-14'),
                $table,
            );
            self::assertSame($table, $rate->tableNumber());
        }
    }
}
