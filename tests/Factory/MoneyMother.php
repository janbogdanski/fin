<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;

final class MoneyMother
{
    public static function usd(string $amount): Money
    {
        return Money::of($amount, CurrencyCode::USD);
    }

    public static function pln(string $amount): Money
    {
        return Money::of($amount, CurrencyCode::PLN);
    }

    public static function eur(string $amount): Money
    {
        return Money::of($amount, CurrencyCode::EUR);
    }

    public static function zero(CurrencyCode $currency = CurrencyCode::PLN): Money
    {
        return Money::zero($currency);
    }
}
