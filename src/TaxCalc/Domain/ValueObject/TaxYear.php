<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\ValueObject;

final readonly class TaxYear
{
    private function __construct(
        public int $value,
    ) {
    }

    public static function of(int $year): self
    {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException("Tax year must be between 2000 and 2100, got: {$year}");
        }

        return new self($year);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
