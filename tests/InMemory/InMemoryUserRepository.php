<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->toString()] = $user;
    }

    public function flush(): void
    {
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->toString()] ?? null;
    }

    public function findByIdForUpdate(UserId $id): ?User
    {
        return $this->findById($id);
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === strtolower(trim($email))) {
                return $user;
            }
        }

        return null;
    }

    public function findByMagicLinkToken(string $token): ?User
    {
        $hashed = hash('sha256', $token);

        foreach ($this->users as $user) {
            if ($user->magicLinkToken()?->token() === $hashed) {
                return $user;
            }
        }

        return null;
    }

    public function findByReferralCode(string $referralCode): ?User
    {
        foreach ($this->users as $user) {
            if ($user->referralCode() === $referralCode) {
                return $user;
            }
        }

        return null;
    }

    public function findByReferralCodeForUpdate(string $referralCode): ?User
    {
        return $this->findByReferralCode($referralCode);
    }

    public function anonymizeUser(UserId $id, \DateTimeImmutable $now): void
    {
        // The application layer already mutates the in-memory aggregate before
        // calling this persistence hook. In Doctrine this method performs the
        // atomic SQL wipe, so the in-memory adapter must not re-apply domain
        // anonymization or it would fail on a second call.
    }

    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}
