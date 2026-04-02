<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\MagicLinkMailerPort;
use App\Identity\Application\Port\MagicLinkTokenGeneratorPort;
use App\Identity\Domain\Repository\UserRepositoryInterface;

/**
 * Generates a magic link token and sends it via email.
 *
 * Security: silently ignores unknown emails to prevent user enumeration.
 */
final readonly class RequestMagicLinkHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MagicLinkTokenGeneratorPort $tokenGenerator,
        private MagicLinkMailerPort $mailer,
    ) {
    }

    public function __invoke(RequestMagicLink $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        if ($user === null) {
            return;
        }

        $token = $this->tokenGenerator->generate($user);
        $user->setMagicLinkToken($token);
        $this->userRepository->save($user);

        $this->mailer->sendMagicLink($user->email(), $token);
    }
}
