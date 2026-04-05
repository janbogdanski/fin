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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final readonly class RequestMagicLinkController
{
    public function __construct(
        private RequestMagicLinkHandler $requestHandler,
        private RateLimiterFactory $magicLinkLimiter,
        private RateLimiterFactory $magicLinkIpLimiter,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    #[Route('/login', name: 'auth_login_submit', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $loginUrl = $this->urlGenerator->generate('auth_login');

        $csrfToken = new CsrfToken('magic_link', (string) $request->request->get('_csrf_token', ''));

        if (! $this->csrfTokenManager->isTokenValid($csrfToken)) {
            $this->addFlash($request, 'error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return new RedirectResponse($loginUrl);
        }

        $email = trim((string) $request->request->get('email', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash($request, 'error', 'Podaj prawidlowy adres e-mail.');

            return new RedirectResponse($loginUrl);
        }

        // Rate limit by IP (broader, prevents distributed attacks)
        $ipLimiter = $this->magicLinkIpLimiter->create((string) $request->getClientIp());

        if (! $ipLimiter->consume()->isAccepted()) {
            $this->addFlash($request, 'error', 'Zbyt wiele prob logowania. Sprobuj ponownie za kilka minut.');

            return new RedirectResponse($loginUrl);
        }

        // Rate limit by email (stricter, prevents targeting a single account)
        $limiter = $this->magicLinkLimiter->create(strtolower($email));

        if (! $limiter->consume()->isAccepted()) {
            $this->addFlash($request, 'error', 'Zbyt wiele prob logowania. Sprobuj ponownie za kilka minut.');

            return new RedirectResponse($loginUrl);
        }

        ($this->requestHandler)(new RequestMagicLink(strtolower($email)));

        return new Response($this->twig->render('auth/email_sent.html.twig', [
            'email' => $email,
        ]));
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        \assert($session instanceof Session);
        $session->getFlashBag()->add($type, $message);
    }
}
