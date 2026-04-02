<?php

declare(strict_types=1);

namespace App\Billing\Domain\ValueObject;

enum ProductCode: string
{
    case STANDARD = 'STANDARD';
    case PRO = 'PRO';

    public function amountCents(): int
    {
        return match ($this) {
            self::STANDARD => 9900,
            self::PRO => 19900,
        };
    }

    public function currency(): string
    {
        return 'PLN';
    }

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'TaxPilot Standard',
            self::PRO => 'TaxPilot Pro',
        };
    }

    /**
     * Returns true if this product tier covers at least the given tier.
     * PRO covers STANDARD, each tier covers itself.
     */
    public function coversAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::STANDARD => 1,
            self::PRO => 2,
        };
    }
}
