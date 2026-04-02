<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Dane wejsciowe do generatora PIT-38 XML.
 *
 * Wszystkie kwoty jako string (reprezentacja BigDecimal) —
 * zaokraglanie odbywa sie w warstwie TaxCalc, tu trafiaja gotowe wartosci.
 */
final readonly class PIT38Data
{
    public function __construct(
        public int $taxYear,
        public string $nip,
        public string $firstName,
        public string $lastName,
        // Sekcja C: odplatne zbycie papierow wartosciowych
        public string $equityProceeds,
        public string $equityCosts,
        public string $equityIncome,
        public string $equityLoss,
        public string $equityTaxBase,
        public string $equityTax,
        // Sekcja D: dywidendy zagraniczne
        public string $dividendGross,
        public string $dividendWHT,
        public string $dividendTaxDue,
        // Kryptowaluty
        public string $cryptoProceeds,
        public string $cryptoCosts,
        public string $cryptoIncome,
        public string $cryptoLoss,
        public string $cryptoTax,
        // Suma
        public string $totalTax,
        public bool $isCorrection,
    ) {
    }
}
