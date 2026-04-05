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

    /**
     * Loads the user and acquires a pessimistic write lock (SELECT … FOR UPDATE).
     *
     * Must be called inside a transaction (use transactional()) to prevent TOCTOU
     * race conditions when multiple concurrent requests attempt to apply a referral
     * for the same user simultaneously.
     */
    public function findByIdForUpdate(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByMagicLinkToken(string $token): ?User;

    public function findByReferralCode(string $referralCode): ?User;

    /**
     * Loads the referrer by referral code and acquires a pessimistic write lock.
     *
     * Must be called inside transactional(). Prevents concurrent transactions from
     * applying the same referral code simultaneously and bypassing the bonus cap.
     */
    public function findByReferralCodeForUpdate(string $referralCode): ?User;

    /**
     * Anonymizes all PII columns for the given user in a single atomic UPDATE.
     *
     * Bypasses Doctrine's ORM to ensure all columns (including encrypted ones)
     * are wiped in a single statement, regardless of entity state.
     *
     * GDPR art. 17 — right to erasure.
     */
    public function anonymizeUser(UserId $id, \DateTimeImmutable $now): void;

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
