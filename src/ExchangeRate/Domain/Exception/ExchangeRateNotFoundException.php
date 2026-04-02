<?php

declare(strict_types=1);

namespace App\ExchangeRate\Domain\Exception;

use App\Shared\Domain\ValueObject\CurrencyCode;

final class ExchangeRateNotFoundException extends \RuntimeException
{
    public static function forDate(CurrencyCode $currency, \DateTimeImmutable $date): self
    {
        return new self(sprintf(
            'Exchange rate for %s not found within 7 business days before %s.',
            $currency->value,
            $date->format('Y-m-d'),
        ));
    }
}
