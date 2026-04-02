<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;

final class User
{
    private ?string $loginToken = null;

    private ?\DateTimeImmutable $loginTokenExpiresAt = null;

    private function __construct(
        private readonly UserId $id,
        private readonly string $email,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(UserId $id, string $email, \DateTimeImmutable $createdAt): self
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
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

    public function setMagicLinkToken(MagicLinkToken $token): void
    {
        $this->loginToken = $token->token();
        $this->loginTokenExpiresAt = $token->expiresAt();
    }

    public function magicLinkToken(): ?MagicLinkToken
    {
        if ($this->loginToken === null || $this->loginTokenExpiresAt === null) {
            return null;
        }

        return MagicLinkToken::create($this->loginToken, $this->loginTokenExpiresAt);
    }

    public function consumeMagicLinkToken(): void
    {
        $this->loginToken = null;
        $this->loginTokenExpiresAt = null;
    }
}
