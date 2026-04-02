<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

/**
 * Value object representing a time-limited, single-use magic link token.
 * Embedded in the User entity (not a standalone Doctrine entity).
 */
final readonly class MagicLinkToken
{
    private function __construct(
        private string $token,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function create(string $token, \DateTimeImmutable $expiresAt): self
    {
        if ($token === '') {
            throw new \InvalidArgumentException('Magic link token cannot be empty.');
        }

        return new self($token, $expiresAt);
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now >= $this->expiresAt;
    }
}
