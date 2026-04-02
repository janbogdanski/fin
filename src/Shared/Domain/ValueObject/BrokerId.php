<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class BrokerId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function of(string $value): self
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            throw new \InvalidArgumentException('Broker ID cannot be empty');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
