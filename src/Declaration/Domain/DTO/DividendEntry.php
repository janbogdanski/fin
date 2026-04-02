<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

use App\Shared\Domain\ValueObject\CountryCode;

/**
 * Pojedyncza dywidenda do raportu audytowego.
 */
final readonly class DividendEntry
{
    public function __construct(
        public \DateTimeImmutable $payDate,
        public string $instrumentName,
        public CountryCode $countryCode,
        public string $grossAmountPLN,
        public string $whtPLN,
        public string $netAmountPLN,
        public string $nbpRate,
        public string $nbpTableNumber,
    ) {
    }
}
