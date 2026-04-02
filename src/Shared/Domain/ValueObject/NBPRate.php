<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Brick\Math\BigDecimal;

/**
 * Kurs średni NBP — value object.
 *
 * Podstawa prawna: art. 11a ust. 1 ustawy o PIT
 * "Przychody w walutach obcych przelicza się na złote według kursu średniego
 *  ogłaszanego przez Narodowy Bank Polski z ostatniego dnia roboczego
 *  poprzedzającego dzień uzyskania przychodu."
 *
 * @see https://api.nbp.pl/
 */
final readonly class NBPRate
{
    private function __construct(
        private CurrencyCode $currency,
        private BigDecimal $rate,
        private \DateTimeImmutable $effectiveDate,
        private string $tableNumber,
    ) {
    }

    public static function create(
        CurrencyCode $currency,
        BigDecimal $rate,
        \DateTimeImmutable $effectiveDate,
        string $tableNumber,
    ): self {
        if ($rate->isNegative() || $rate->isZero()) {
            throw new \InvalidArgumentException("NBP rate must be positive, got: {$rate}");
        }

        if (! preg_match('/^\d{3}\/[ABC]\/NBP\/\d{4}$/', $tableNumber)) {
            throw new \InvalidArgumentException("Invalid NBP table number: {$tableNumber}");
        }

        return new self($currency, $rate, $effectiveDate, $tableNumber);
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function rate(): BigDecimal
    {
        return $this->rate;
    }

    public function effectiveDate(): \DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function tableNumber(): string
    {
        return $this->tableNumber;
    }
}
