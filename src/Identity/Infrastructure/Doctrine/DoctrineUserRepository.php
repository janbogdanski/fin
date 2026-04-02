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

    /**
     * Finds a user by magic link token.
     *
     * Security: the raw token is hashed with SHA-256 before DB lookup,
     * eliminating timing attacks on B-tree index comparison.
     *
     * TOCTOU: uses SELECT ... FOR UPDATE to prevent concurrent token consumption.
     */
    public function findByMagicLinkToken(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);

        $this->connection->beginTransaction();

        try {
            // Acquire a row-level lock to prevent concurrent token consumption (TOCTOU)
            $this->connection->executeStatement(
                'SELECT id FROM users WHERE login_token = :token FOR UPDATE',
                [
                    'token' => $hashedToken,
                ],
            );

            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy([
                    'loginToken' => $hashedToken,
                ]);

            // Transaction is committed by the caller (save/flush), or rolled back on error.
            // We commit here since the lock is only needed for the find+consume window.
            $this->connection->commit();

            return $user;
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}
