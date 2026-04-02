<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    /**
     * Commits pending changes to the persistence layer.
     * Should be called from the Application layer (handlers), not from within the repository.
     */
    public function flush(): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByMagicLinkToken(string $token): ?User;

    /**
     * Executes the given callback within a single database transaction.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
