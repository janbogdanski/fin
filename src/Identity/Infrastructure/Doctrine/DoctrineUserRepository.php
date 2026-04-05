<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    private Connection $connection;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->connection = $this->entityManager->getConnection();
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => strtolower(trim($email)),
            ]);
    }

    public function findByReferralCode(string $referralCode): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'referralCode' => $referralCode,
            ]);
    }

    /**
     * Finds a user by magic link token.
     *
     * Security: the raw token is hashed with SHA-256 before DB lookup,
     * eliminating timing attacks on B-tree index comparison.
     *
     * When used inside transactional(), the SELECT FOR UPDATE lock is held
     * until the outer transaction commits — preventing TOCTOU race conditions.
     */
    public function findByMagicLinkToken(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);

        // SELECT FOR UPDATE acquires a row-level lock.
        // The lock is released when the surrounding transaction commits/rolls back.
        // Caller MUST wrap find+consume+flush in transactional() to prevent TOCTOU.
        $this->connection->executeStatement(
            'SELECT id FROM users WHERE login_token = :token FOR UPDATE',
            [
                'token' => $hashedToken,
            ],
        );

        return $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'loginToken' => $hashedToken,
            ]);
    }

    /**
     * Atomically wipes all PII columns for the given user.
     *
     * Uses a direct SQL UPDATE to ensure every column is cleared regardless of
     * Doctrine's identity map or encrypted field types.
     * The anonymized_at timestamp is persisted here; the domain object's
     * anonymize() method sets the in-memory state.
     */
    public function anonymizeUser(UserId $id, \DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE users
                SET email              = :email,
                    nip                = NULL,
                    first_name         = NULL,
                    last_name          = NULL,
                    login_token        = NULL,
                    login_token_expires_at = NULL,
                    anonymized_at      = :anonymizedAt
                WHERE id = :id
            SQL,
            [
                'email' => 'deleted-' . $id->toString() . '@deleted.invalid',
                'anonymizedAt' => $now->format('Y-m-d H:i:s'),
                'id' => $id->toString(),
            ],
        );
    }

    public function transactional(callable $callback): mixed
    {
        return $this->connection->transactional(static fn () => $callback());
    }
}
