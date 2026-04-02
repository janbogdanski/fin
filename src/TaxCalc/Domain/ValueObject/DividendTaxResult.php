<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;

/**
 * Wynik kalkulacji podatku od dywidendy zagranicznej.
 *
 * Immutable DTO — zawiera pelny audit trail (kurs NBP, stawki).
 *
 * @see art. 30a ust. 1 pkt 4 ustawy o PIT — 19% podatek od dywidend
 * @see art. 30a ust. 2 ustawy o PIT — odliczenie podatku zaplaconego za granica
 */
final readonly class DividendTaxResult
{
    public function __construct(
        public Money $grossDividendPLN,
        public Money $whtPaidPLN,
        public BigDecimal $whtRate,
        public BigDecimal $upoRate,
        public Money $polishTaxDue,
        public CountryCode $sourceCountry,
        public NBPRate $nbpRate,
    ) {
    }
}
