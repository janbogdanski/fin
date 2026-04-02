<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use Brick\Math\BigDecimal;

/**
 * Oblicza podatek od dywidendy zagranicznej dla polskiego rezydenta.
 *
 * Algorytm:
 * 1. grossDividendPLN = grossDividend * kursNBP
 * 2. whtPaidPLN = grossDividendPLN * actualWHTRate
 * 3. polishTax = grossDividendPLN * 19%
 * 4. taxDuePL = max(0, polishTax - whtPaidPLN)
 *
 * Odliczenie podatku zagranicznego NIE moze przekroczyc stawki z UPO
 * (art. 30a ust. 2 ustawy o PIT) — serwis cappuje effectiveWHT do stawki UPO.
 *
 * @see art. 30a ust. 1 pkt 4 ustawy o PIT — 19% podatek od dywidend
 * @see art. 30a ust. 2 ustawy o PIT — odliczenie podatku zagranicznego
 * @see art. 11a ust. 1 ustawy o PIT — przeliczenie kursem NBP
 */
final readonly class DividendTaxService
{
    private const string POLISH_TAX_RATE = '0.19';

    public function __construct(
        private UPORegistry $upoRegistry,
        private CurrencyConverterInterface $currencyConverter,
    ) {
    }

    public function calculate(
        Money $grossDividend,
        NBPRate $nbpRate,
        CountryCode $sourceCountry,
        BigDecimal $actualWHTRate,
    ): DividendTaxResult {
        $this->assertValidWHTRate($actualWHTRate);

        $grossDividendPLN = $this->currencyConverter->toPLN($grossDividend, $nbpRate);

        $whtPaidPLN = Money::of(
            $grossDividendPLN->amount()->multipliedBy($actualWHTRate),
            CurrencyCode::PLN,
        );

        $upoRate = $this->upoRegistry->getRate($sourceCountry);

        // art. 30a ust. 2 PIT: odliczenie WHT nie moze przekroczyc stawki z UPO
        $effectiveWHTRate = BigDecimal::min($actualWHTRate, $upoRate);

        $polishTax = $grossDividendPLN->amount()->multipliedBy(self::POLISH_TAX_RATE);
        $whtDeduction = $grossDividendPLN->amount()->multipliedBy($effectiveWHTRate);
        $taxDue = $polishTax->minus($whtDeduction);

        $taxDuePL = $taxDue->isNegative()
            ? Money::zero(CurrencyCode::PLN)
            : Money::of($taxDue, CurrencyCode::PLN);

        return new DividendTaxResult(
            grossDividendPLN: $grossDividendPLN,
            whtPaidPLN: $whtPaidPLN,
            whtRate: $actualWHTRate,
            upoRate: $upoRate,
            polishTaxDue: $taxDuePL,
            sourceCountry: $sourceCountry,
            nbpRate: $nbpRate,
        );
    }

    private function assertValidWHTRate(BigDecimal $rate): void
    {
        if ($rate->isNegative()) {
            throw new \InvalidArgumentException("WHT rate cannot be negative, got: {$rate}");
        }

        if ($rate->isGreaterThan(BigDecimal::one())) {
            throw new \InvalidArgumentException("WHT rate cannot exceed 100% (1.0), got: {$rate}");
        }
    }
}
