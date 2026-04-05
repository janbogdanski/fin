<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class VerifyMagicLinkController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The actual authentication is handled by MagicLinkAuthenticator.
     * This route exists only as a target for the authenticator's supports() method.
     */
    #[Route('/auth/verify/{token}', name: 'auth_verify', methods: ['GET'])]
    public function __invoke(): Response
    {
        // This code is never reached -- MagicLinkAuthenticator intercepts the request.
        // If somehow reached (e.g., authenticator not configured), redirect to login.
        return new RedirectResponse($this->urlGenerator->generate('auth_login'));
    }
}
