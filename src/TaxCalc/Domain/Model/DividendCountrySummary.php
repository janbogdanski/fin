<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\CountryCode;
use Brick\Math\BigDecimal;

/**
 * Podsumowanie dywidend z jednego kraju — sekcja D PIT-38.
 * Immutable DTO.
 */
final readonly class DividendCountrySummary
{
    public function __construct(
        public CountryCode $country,
        public BigDecimal $grossDividendPLN,
        public BigDecimal $whtPaidPLN,
        public BigDecimal $polishTaxDue,
    ) {
    }

    public function add(self $other): self
    {
        if ($this->country !== $other->country) {
            throw new \InvalidArgumentException(
                "Cannot merge dividend summaries from different countries: {$this->country->value} vs {$other->country->value}",
            );
        }

        return new self(
            country: $this->country,
            grossDividendPLN: $this->grossDividendPLN->plus($other->grossDividendPLN),
            whtPaidPLN: $this->whtPaidPLN->plus($other->whtPaidPLN),
            polishTaxDue: $this->polishTaxDue->plus($other->polishTaxDue),
        );
    }
}
