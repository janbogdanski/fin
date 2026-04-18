<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Dane wejsciowe do generatora PIT-38 XML.
 *
 * Wszystkie kwoty jako string (reprezentacja BigDecimal) —
 * zaokraglanie odbywa sie w warstwie TaxCalc, tu trafiaja gotowe wartosci.
 *
 * Pola adresowe i kodUrzedu sa opcjonalne — wymagane do generacji XML zgodnego
 * z oficjalnym XSD MF (PIT-38(18)). Bez nich XML jest poprawny strukturalnie,
 * ale nie przejdzie walidacji officjalnego schematu.
 */
final readonly class PIT38Data
{
    /**
     * @param string|null       $nip                null = user has not provided NIP yet (preview only); mutually exclusive with $pesel
     * @param string|null       $pesel              null = user has not provided PESEL yet; mutually exclusive with $nip
     * @param string|null       $firstName          null = user has not provided name yet (preview only)
     * @param string|null       $lastName           null = user has not provided name yet (preview only)
     * @param string|null       $kodUrzedu          4-znakowy kod urzedu skarbowego (wymagany do zlozenia)
     * @param PolishAddress|null $adresZamieszkania  adres zamieszkania (wymagany do zlozenia)
     * @param string|null       $dateOfBirth        data urodzenia w formacie YYYY-MM-DD (wymagana przez oficjalny XSD MF)
     */
    public function __construct(
        public int $taxYear,
        public ?string $nip,
        public ?string $firstName,
        public ?string $lastName,
        // Sekcja C: odplatne zbycie papierow wartosciowych (art. 30b ust. 1)
        public string $equityProceeds,
        public string $equityCosts,
        public string $equityIncome,
        public string $equityLoss,
        public string $equityTaxBase,
        public string $equityTax,
        // Dywidendy zagraniczne (art. 30a ust. 1)
        public string $dividendGross,
        public string $dividendWHT,
        public string $dividendTaxDue,
        // Kryptowaluty (art. 30b ust. 1a)
        public string $cryptoProceeds,
        public string $cryptoCosts,
        public string $cryptoIncome,
        public string $cryptoLoss,
        public string $cryptoTax,
        // Suma
        public string $totalTax,
        public bool $isCorrection,
        // Dane do zlozenia (opcjonalne — wymagane przez oficjalny XSD MF)
        public ?string $kodUrzedu = null,
        public ?PolishAddress $adresZamieszkania = null,
        public ?string $dateOfBirth = null,
        public ?string $pesel = null,
    ) {
        if ($nip !== null) {
            $this->validateNip($nip);
        }

        if ($pesel !== null) {
            $this->validatePesel($pesel);
        }

        $this->validateTaxYear($taxYear);

        if ($firstName !== null) {
            $this->validateName($firstName, 'First name');
        }

        if ($lastName !== null) {
            $this->validateName($lastName, 'Last name');
        }

        if ($dateOfBirth !== null) {
            $this->validateDateOfBirth($dateOfBirth);
        }
    }

    /**
     * Whether the user has provided all personal data required for XML generation.
     * Requires first name, last name and either NIP or PESEL.
     */
    public function hasCompletePersonalData(): bool
    {
        return ($this->nip !== null || $this->pesel !== null)
            && $this->firstName !== null
            && $this->lastName !== null;
    }

    /**
     * Whether address data required by the official PIT-38(18) XSD is complete.
     */
    public function hasCompleteAddress(): bool
    {
        return $this->adresZamieszkania !== null;
    }

    /**
     * Whether all fields required by the official PIT-38(18) XSD are present.
     * Use this to gate generation of XML that will be submitted to the tax authority.
     */
    public function hasCompleteFilingData(): bool
    {
        return $this->hasCompletePersonalData()
            && $this->dateOfBirth !== null
            && $this->kodUrzedu !== null
            && $this->adresZamieszkania !== null;
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

    private function validatePesel(string $pesel): void
    {
        if (! preg_match('/^\d{11}$/', $pesel)) {
            throw new \InvalidArgumentException('PESEL must be exactly 11 digits');
        }

        $weights = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
        $sum = 0;

        for ($i = 0; $i < 10; ++$i) {
            $sum += (int) $pesel[$i] * $weights[$i];
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        if ($checkDigit !== (int) $pesel[10]) {
            throw new \InvalidArgumentException('Invalid PESEL check digit');
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

    private function validateDateOfBirth(string $dateOfBirth): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
            throw new \InvalidArgumentException('Date of birth must be in format YYYY-MM-DD');
        }
    }
}
