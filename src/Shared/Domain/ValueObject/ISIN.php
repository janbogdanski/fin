<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class ISIN
{
    private function __construct(
        private string $value,
    ) {
    }

    /**
     * Creates an ISIN-like key from any non-empty string without format or Luhn validation.
     *
     * FOR INTERNAL USE ONLY — use this exclusively when a broker does not provide a real ISIN
     * (e.g. XTB exports only a ticker/symbol such as "AAPL.US"). The returned instance carries
     * the raw symbol as its value and acts as a grouping key inside the FIFO pipeline.
     * Never expose instances created by this factory to external systems or persistence layers
     * that expect a standards-compliant ISO 6166 ISIN.
     *
     * @throws \InvalidArgumentException if $value is empty after trimming
     */
    public static function fromUnchecked(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Instrument key must not be empty.');
        }

        return new self($trimmed);
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $normalized)) {
            throw new \InvalidArgumentException("Invalid ISIN format: {$value}");
        }

        if (! self::isValidCheckDigit($normalized)) {
            throw new \InvalidArgumentException("Invalid ISIN check digit: {$value}");
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function countryCode(): string
    {
        return substr($this->value, 0, 2);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * ISIN Luhn check digit validation (ISO 6166).
     * Convert alpha to digits (A=10, B=11, ..., Z=35), then Luhn mod-10.
     */
    private static function isValidCheckDigit(string $isin): bool
    {
        $digits = '';
        for ($i = 0; $i < strlen($isin); $i++) {
            $char = $isin[$i];
            if (ctype_alpha($char)) {
                $digits .= (string) (ord($char) - ord('A') + 10);
            } else {
                $digits .= $char;
            }
        }

        // Luhn algorithm
        $sum = 0;
        $length = strlen($digits);
        $parity = $length % 2;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if (($i % 2) === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return ($sum % 10) === 0;
    }
}
