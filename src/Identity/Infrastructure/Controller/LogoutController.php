<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class LogoutController
{
    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function __invoke(): never
    {
        // Handled by Symfony security firewall logout config.
        throw new \LogicException('This should never be reached.');
    }
}
