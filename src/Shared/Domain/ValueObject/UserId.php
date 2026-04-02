<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * ADR: UserId wraps Symfony Uid (UUIDv7) in a domain value object.
 * This decouples domain code from the Symfony Uid component — only this class
 * depends on Symfony\Component\Uid\Uuid. If we ever switch UUID libraries,
 * only this file changes. The trade-off (thin wrapper vs. direct Uuid usage)
 * is accepted for domain purity and testability (generate() vs. fromString()).
 */
final readonly class UserId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
