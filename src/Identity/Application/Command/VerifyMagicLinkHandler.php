<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Application\Exception\MagicLinkExpiredException;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Validates a magic link token and returns the authenticated User.
 *
 * Token is single-use: consumed immediately after successful validation.
 */
final readonly class VerifyMagicLinkHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Wraps find + consume + flush in a single transaction so the
     * SELECT FOR UPDATE lock is held until the token is consumed.
     */
    public function __invoke(VerifyMagicLink $command): User
    {
        return $this->userRepository->transactional(function () use ($command): User {
            $user = $this->userRepository->findByMagicLinkToken($command->token);

            if ($user === null) {
                throw new MagicLinkInvalidException();
            }

            if ($user->isMagicLinkTokenExpired($this->clock->now())) {
                throw new MagicLinkExpiredException();
            }

            $user->consumeMagicLinkToken();
            $this->userRepository->save($user);
            $this->userRepository->flush();

            return $user;
        });
    }
}
