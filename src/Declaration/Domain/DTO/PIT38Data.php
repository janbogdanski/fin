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
        $this->validateNip($nip);
        $this->validateTaxYear($taxYear);
        $this->validateName($firstName, 'First name');
        $this->validateName($lastName, 'Last name');
    }

    private function validateNip(string $nip): void
    {
        if (! preg_match('/^\d{10}$/', $nip)) {
            throw new \InvalidArgumentException('NIP must be exactly 10 digits');
        }

        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; ++$i) {
            $sum += (int) $nip[$i] * $weights[$i];
        }

        $checkDigit = $sum % 11;

        // Check digit of 10 means the NIP is invalid (no valid single digit representation)
        if ($checkDigit === 10 || $checkDigit !== (int) $nip[9]) {
            throw new \InvalidArgumentException('Invalid NIP check digit');
        }
    }

    private function validateTaxYear(int $taxYear): void
    {
        if ($taxYear < 2000 || $taxYear > 2100) {
            throw new \InvalidArgumentException('Tax year must be between 2000 and 2100');
        }
    }

    private function validateName(string $name, string $fieldLabel): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(sprintf('%s must not be empty', $fieldLabel));
        }
    }
}
