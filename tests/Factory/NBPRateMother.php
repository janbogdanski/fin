<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;

final class NBPRateMother
{
    /**
     * USD/PLN ~ 4.05, realistic rate.
     */
    public static function usd405(?\DateTimeImmutable $effectiveDate = null): NBPRate
    {
        return self::forCurrency(
            code: CurrencyCode::USD,
            rate: '4.0500',
            date: $effectiveDate ?? new \DateTimeImmutable('2025-01-14'),
        );
    }

    /**
     * EUR/PLN ~ 4.60, realistic rate.
     */
    public static function eur460(?\DateTimeImmutable $effectiveDate = null): NBPRate
    {
        return self::forCurrency(
            code: CurrencyCode::EUR,
            rate: '4.6000',
            date: $effectiveDate ?? new \DateTimeImmutable('2025-01-14'),
        );
    }

    public static function forCurrency(
        CurrencyCode $code,
        string $rate,
        ?\DateTimeImmutable $date = null,
        string $tableNumber = '010/A/NBP/2025',
    ): NBPRate {
        return NBPRate::create(
            currency: $code,
            rate: BigDecimal::of($rate),
            effectiveDate: $date ?? new \DateTimeImmutable('2025-01-14'),
            tableNumber: $tableNumber,
        );
    }
}
