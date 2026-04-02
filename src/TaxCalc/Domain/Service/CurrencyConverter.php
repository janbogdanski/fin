<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\CurrencyMismatchException;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;

/**
 * Przelicza Money na PLN kursem NBP.
 * Żyje w TaxCalc domain (nie w Shared) — bo zna NBPRate.
 *
 * @see art. 11a ust. 1 ustawy o PIT
 */
final readonly class CurrencyConverter implements CurrencyConverterInterface
{
    /**
     * @throws CurrencyMismatchException gdy waluta Money != waluta NBPRate
     */
    public function toPLN(Money $money, NBPRate $rate): Money
    {
        if ($money->currency()->equals(CurrencyCode::PLN)) {
            return $money;
        }

        if (! $money->currency()->equals($rate->currency())) {
            throw new CurrencyMismatchException($money->currency(), $rate->currency());
        }

        return Money::of(
            $money->amount()->multipliedBy($rate->rate()),
            CurrencyCode::PLN,
        );
    }
}
