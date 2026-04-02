<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;

final class User
{
    private ?string $loginToken = null;

    private ?\DateTimeImmutable $loginTokenExpiresAt = null;

    private ?string $nip = null;

    private ?string $firstName = null;

    private ?string $lastName = null;

    private function __construct(
        private readonly UserId $id,
        private readonly string $email,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(UserId $id, string $email, \DateTimeImmutable $createdAt): self
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided.');
        }

        return new self($id, strtolower(trim($email)), $createdAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function nip(): ?string
    {
        return $this->nip;
    }

    public function firstName(): ?string
    {
        return $this->firstName;
    }

    public function lastName(): ?string
    {
        return $this->lastName;
    }

    public function updateProfile(string $nip, string $firstName, string $lastName): void
    {
        $this->validateNip($nip);

        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if ($firstName === '') {
            throw new \InvalidArgumentException('First name must not be empty');
        }

        if ($lastName === '') {
            throw new \InvalidArgumentException('Last name must not be empty');
        }

        $this->nip = $nip;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function hasCompleteProfile(): bool
    {
        return $this->nip !== null && $this->firstName !== null && $this->lastName !== null;
    }

    public function setMagicLinkToken(MagicLinkToken $token): void
    {
        $this->loginToken = hash('sha256', $token->token());
        $this->loginTokenExpiresAt = $token->expiresAt();
    }

    /**
     * Returns the stored magic link token (hashed) or null if none is set.
     *
     * Note: the token value inside the returned object is a SHA-256 hash,
     * NOT the raw token. This is intentional — the raw token is never persisted.
     * Use {@see isMagicLinkTokenExpired()} instead of calling isExpired() on the returned object.
     */
    public function magicLinkToken(): ?MagicLinkToken
    {
        if ($this->loginToken === null || $this->loginTokenExpiresAt === null) {
            return null;
        }

        return MagicLinkToken::create($this->loginToken, $this->loginTokenExpiresAt);
    }

    /**
     * Checks whether the current magic link token is expired or absent.
     * Prefer this over magicLinkToken()->isExpired() to avoid semantic confusion.
     */
    public function isMagicLinkTokenExpired(): bool
    {
        $token = $this->magicLinkToken();

        return $token === null || $token->isExpired();
    }

    public function consumeMagicLinkToken(): void
    {
        $this->loginToken = null;
        $this->loginTokenExpiresAt = null;
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

        if ($checkDigit === 10 || $checkDigit !== (int) $nip[9]) {
            throw new \InvalidArgumentException('Invalid NIP check digit');
        }
    }
}
