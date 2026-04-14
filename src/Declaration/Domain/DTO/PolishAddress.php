<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Adres zamieszkania podatnika na terytorium Polski.
 *
 * Odpowiada strukturze AdresPol z oficjalnego XSD MF (PIT-38(18)).
 * Walidacja formatu kodu pocztowego jest egzekwowana przy konstrukcji.
 */
final readonly class PolishAddress
{
    /**
     * @param string      $miejscowosc  miejscowosc (wymagana)
     * @param string      $nrDomu       numer domu (wymagany)
     * @param string      $kodPocztowy  kod pocztowy w formacie XX-XXX (wymagany)
     * @param string      $wojewodztwo  wojewodztwo (wymagane)
     * @param string      $powiat       powiat (wymagany)
     * @param string      $gmina        gmina (wymagana)
     * @param string|null $ulica        nazwa ulicy (opcjonalna)
     * @param string|null $nrLokalu     numer lokalu (opcjonalny)
     */
    public function __construct(
        public string $miejscowosc,
        public string $nrDomu,
        public string $kodPocztowy,
        public string $wojewodztwo,
        public string $powiat,
        public string $gmina,
        public ?string $ulica = null,
        public ?string $nrLokalu = null,
    ) {
        if (! preg_match('/^\d{2}-\d{3}$/', $this->kodPocztowy)) {
            throw new \InvalidArgumentException('KodPocztowy must be in format XX-XXX (e.g. 00-001)');
        }
    }
}
