<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Controller;

use App\Identity\Application\Command\RequestMagicLink;
use App\Identity\Application\Command\RequestMagicLinkHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class AuthController
{
    public function __construct(
        private RequestMagicLinkHandler $requestHandler,
        private RateLimiterFactory $magicLinkLimiter,
        private Environment $twig,
    ) {
    }

    #[Route('/login', name: 'auth_login', methods: ['GET'])]
    public function login(): Response
    {
        return new Response($this->twig->render('auth/login.html.twig'));
    }

    #[Route('/login', name: 'auth_login_submit', methods: ['POST'])]
    public function requestMagicLink(Request $request): Response
    {
        $email = trim((string) $request->request->get('email', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash($request, 'error', 'Podaj prawidlowy adres e-mail.');

            return new RedirectResponse('/login');
        }

        $limiter = $this->magicLinkLimiter->create(strtolower($email));

        if (! $limiter->consume()->isAccepted()) {
            $this->addFlash($request, 'error', 'Zbyt wiele prob logowania. Sprobuj ponownie za kilka minut.');

            return new RedirectResponse('/login');
        }

        ($this->requestHandler)(new RequestMagicLink(strtolower($email)));

        return new Response($this->twig->render('auth/email_sent.html.twig', [
            'email' => $email,
        ]));
    }

    /**
     * The actual authentication is handled by MagicLinkAuthenticator.
     * This route exists only as a target for the authenticator's supports() method.
     */
    #[Route('/auth/verify/{token}', name: 'auth_verify', methods: ['GET'])]
    public function verify(): Response
    {
        // This code is never reached -- MagicLinkAuthenticator intercepts the request.
        // If somehow reached (e.g., authenticator not configured), redirect to login.
        return new RedirectResponse('/login');
    }

    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Handled by Symfony security firewall logout config.
        throw new \LogicException('This should never be reached.');
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        \assert($session instanceof Session);
        $session->getFlashBag()->add($type, $message);
    }
}
