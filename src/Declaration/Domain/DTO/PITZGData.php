<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

use App\Shared\Domain\ValueObject\CountryCode;

/**
 * Dane wejsciowe do generatora PIT/ZG — zalacznik o dochodach zagranicznych.
 * Jeden obiekt per kraj.
 */
final readonly class PITZGData
{
    public function __construct(
        public int $taxYear,
        public string $nip,
        public string $firstName,
        public string $lastName,
        public CountryCode $countryCode,
        public string $incomeGross,
        public string $taxPaidAbroad,
        public bool $isCorrection,
    ) {
    }
}
