<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\CountryCode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

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
        public BigDecimal $effectiveWHTRate,
    ) {
    }

    public function add(self $other): self
    {
        if ($this->country !== $other->country) {
            throw new \InvalidArgumentException(
                "Cannot merge dividend summaries from different countries: {$this->country->value} vs {$other->country->value}",
            );
        }

        $newGross = $this->grossDividendPLN->plus($other->grossDividendPLN);
        $newWht = $this->whtPaidPLN->plus($other->whtPaidPLN);

        $newEffectiveRate = $newGross->isZero()
            ? BigDecimal::zero()
            : $newWht->dividedBy($newGross, 6, RoundingMode::HALF_UP);

        return new self(
            country: $this->country,
            grossDividendPLN: $newGross,
            whtPaidPLN: $newWht,
            polishTaxDue: $this->polishTaxDue->plus($other->polishTaxDue),
            effectiveWHTRate: $newEffectiveRate,
        );
    }
}
