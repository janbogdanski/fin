<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Kod kraju ISO 3166-1 alpha-2.
 * Najczestsze kraje dla inwestorow gieldowych z polskiej perspektywy.
 */
enum CountryCode: string
{
    case PL = 'PL';
    case US = 'US';
    case GB = 'GB';
    case DE = 'DE';
    case IE = 'IE';
    case NL = 'NL';
    case CH = 'CH';
    case CA = 'CA';
    case JP = 'JP';
    case AU = 'AU';
    case LU = 'LU';
    case FR = 'FR';
    case SE = 'SE';
    case NO = 'NO';
    case DK = 'DK';
    case FI = 'FI';
    case AT = 'AT';
    case ES = 'ES';
    case IT = 'IT';
    case BE = 'BE';
    case HK = 'HK';
    case SG = 'SG';
    case KR = 'KR';
    case TW = 'TW';
    case CN = 'CN';

    public static function fromString(string $code): self
    {
        $normalized = strtoupper(trim($code));

        if (strlen($normalized) !== 2) {
            throw new \InvalidArgumentException(
                "Country code must be exactly 2 characters (ISO 3166-1 alpha-2), got: '{$code}'",
            );
        }

        $case = self::tryFrom($normalized);

        if ($case === null) {
            throw new \InvalidArgumentException(
                "Unsupported country code: '{$normalized}'. Add it to CountryCode enum if needed.",
            );
        }

        return $case;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
