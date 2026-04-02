<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Application\Exception\MagicLinkExpiredException;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;

/**
 * Validates a magic link token and returns the authenticated User.
 *
 * Token is single-use: consumed immediately after successful validation.
 */
final readonly class VerifyMagicLinkHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(VerifyMagicLink $command): User
    {
        $user = $this->userRepository->findByMagicLinkToken($command->token);

        if ($user === null) {
            throw new MagicLinkInvalidException();
        }

        $token = $user->magicLinkToken();

        if ($token === null || $token->isExpired()) {
            throw new MagicLinkExpiredException();
        }

        $user->consumeMagicLinkToken();
        $this->userRepository->save($user);

        return $user;
    }
}
