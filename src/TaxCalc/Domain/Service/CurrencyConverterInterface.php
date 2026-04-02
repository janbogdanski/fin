<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;

/**
 * Converts Money to PLN using NBP rate.
 * Interface enables testability via mock injection.
 *
 * @see art. 11a ust. 1 ustawy o PIT
 */
interface CurrencyConverterInterface
{
    public function toPLN(Money $money, NBPRate $rate): Money;
}
