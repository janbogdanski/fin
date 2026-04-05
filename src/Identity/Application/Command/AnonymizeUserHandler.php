<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Domain\Repository\UserRepositoryInterface;

/**
 * Handles the GDPR art. 17 right-to-erasure request for a user.
 *
 * Marks the domain aggregate as anonymized, then issues an atomic SQL UPDATE
 * through the repository port to wipe all PII columns.
 */
final readonly class AnonymizeUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(AnonymizeUser $command): void
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new \DomainException(sprintf('User "%s" not found.', $command->userId->toString()));
        }

        $now = new \DateTimeImmutable();

        $this->userRepository->transactional(function () use ($user, $command, $now): void {
            $user->anonymize($now);
            $this->userRepository->anonymizeUser($command->userId, $now);
            $this->userRepository->flush();
        });
    }
}
