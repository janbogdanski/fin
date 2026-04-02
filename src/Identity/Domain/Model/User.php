<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;

final class User
{
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
}
