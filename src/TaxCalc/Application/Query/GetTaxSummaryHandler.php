<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Query;

use App\TaxCalc\Application\Command\CalculateAnnualTax;
use App\TaxCalc\Application\Command\CalculateAnnualTaxHandler;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use Brick\Math\RoundingMode;

/**
 * Handler — query side. Oblicza (lub odczytuje) i mapuje do flat DTO.
 *
 * Na razie deleguje do CalculateAnnualTaxHandler (brak persistence).
 * Docelowo: odczyt z read modelu / cache.
 */
final readonly class GetTaxSummaryHandler
{
    public function __construct(
        private CalculateAnnualTaxHandler $calculateHandler,
    ) {
    }

    public function __invoke(GetTaxSummary $query): TaxSummaryResult
    {
        $command = new CalculateAnnualTax($query->userId, $query->taxYear);
        $calc = ($this->calculateHandler)($command);

        return self::toResult($calc);
    }

    private static function toResult(AnnualTaxCalculation $calc): TaxSummaryResult
    {
        $dividends = [];
        foreach ($calc->dividendsByCountry() as $code => $summary) {
            $dividends[$code] = new TaxSummaryDividendCountry(
                countryCode: $code,
                grossDividendPLN: self::format($summary->grossDividendPLN),
                whtPaidPLN: self::format($summary->whtPaidPLN),
                polishTaxDue: self::format($summary->polishTaxDue),
            );
        }

        return new TaxSummaryResult(
            taxYear: $calc->taxYear()->value,
            equityProceeds: self::format($calc->equityProceeds()),
            equityCostBasis: self::format($calc->equityCostBasis()),
            equityCommissions: self::format($calc->equityCommissions()),
            equityGainLoss: self::format($calc->equityGainLoss()),
            equityLossDeduction: self::format($calc->equityLossDeduction()),
            equityTaxableIncome: $calc->equityTaxableIncome()->__toString(),
            equityTax: $calc->equityTax()->__toString(),
            dividendsByCountry: $dividends,
            dividendTotalTaxDue: self::format($calc->dividendTotalTaxDue()),
            cryptoProceeds: self::format($calc->cryptoProceeds()),
            cryptoCostBasis: self::format($calc->cryptoCostBasis()),
            cryptoCommissions: self::format($calc->cryptoCommissions()),
            cryptoGainLoss: self::format($calc->cryptoGainLoss()),
            cryptoLossDeduction: self::format($calc->cryptoLossDeduction()),
            cryptoTaxableIncome: $calc->cryptoTaxableIncome()->__toString(),
            cryptoTax: $calc->cryptoTax()->__toString(),
            totalTaxDue: $calc->totalTaxDue()->__toString(),
        );
    }

    private static function format(\Brick\Math\BigDecimal $amount): string
    {
        return $amount->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
