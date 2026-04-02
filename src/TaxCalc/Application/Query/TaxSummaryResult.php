<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Query;

/**
 * Read model — flat DTO z kwotami jako string.
 * Gotowy do serializacji (JSON, Twig).
 * String, nie BigDecimal — bo to warstwa prezentacji.
 */
final readonly class TaxSummaryResult
{
    /**
     * @param array<string, TaxSummaryDividendCountry> $dividendsByCountry key = country code
     */
    public function __construct(
        public int $taxYear,

        // Sekcja C — equity + derivatives
        public string $equityProceeds,
        public string $equityCostBasis,
        public string $equityCommissions,
        public string $equityGainLoss,
        public string $equityLossDeduction,
        public string $equityTaxableIncome,
        public string $equityTax,

        // Sekcja D — dywidendy
        public array $dividendsByCountry,
        public string $dividendTotalTaxDue,

        // Kryptowaluty
        public string $cryptoProceeds,
        public string $cryptoCostBasis,
        public string $cryptoCommissions,
        public string $cryptoGainLoss,
        public string $cryptoLossDeduction,
        public string $cryptoTaxableIncome,
        public string $cryptoTax,

        // Suma
        public string $totalTaxDue,
    ) {
    }
}
