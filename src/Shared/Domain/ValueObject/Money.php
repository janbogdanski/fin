<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Money — value object. BigDecimal + CurrencyCode. Immutable.
 * NIE zna NBPRate — przeliczenie walut jest w TaxCalc\Domain\Service\CurrencyConverter.
 */
final readonly class Money
{
    private function __construct(
        private BigDecimal $amount,
        private CurrencyCode $currency,
    ) {
    }

    /**
     * Factory z pełną precyzją — NIE zaokrągla.
     * Zaokrąglanie do scale 2 dopiero przy: persistence, display, PIT-38.
     */
    public static function of(string|BigDecimal $amount, CurrencyCode $currency): self
    {
        return new self(
            BigDecimal::of($amount),
            $currency,
        );
    }

    public static function zero(CurrencyCode $currency): self
    {
        return new self(BigDecimal::zero(), $currency);
    }

    /**
     * Zaokrąglenie do groszy (scale 2) — wywoływać TYLKO na granicach.
     */
    public function rounded(): self
    {
        return new self(
            $this->amount->toScale(2, RoundingMode::HALF_UP),
            $this->currency,
        );
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    public function multiply(BigDecimal|string $factor): self
    {
        return new self(
            $this->amount->multipliedBy($factor),
            $this->currency,
        );
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function amount(): BigDecimal
    {
        return $this->amount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
