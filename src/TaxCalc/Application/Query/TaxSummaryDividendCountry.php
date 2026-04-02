<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Query;

/**
 * Read model — podsumowanie dywidend z jednego kraju.
 * String amounts — warstwa prezentacji.
 */
final readonly class TaxSummaryDividendCountry
{
    public function __construct(
        public string $countryCode,
        public string $grossDividendPLN,
        public string $whtPaidPLN,
        public string $polishTaxDue,
    ) {
    }
}
