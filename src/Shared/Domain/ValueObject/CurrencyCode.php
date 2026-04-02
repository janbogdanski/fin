<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

enum CurrencyCode: string
{
    case PLN = 'PLN';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case SEK = 'SEK';
    case NOK = 'NOK';
    case DKK = 'DKK';
    case CZK = 'CZK';
    case HUF = 'HUF';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case HKD = 'HKD';
    case TRY = 'TRY';
    case ZAR = 'ZAR';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
