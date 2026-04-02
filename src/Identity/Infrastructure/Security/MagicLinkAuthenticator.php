<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Command\VerifyMagicLink;
use App\Identity\Application\Command\VerifyMagicLinkHandler;
use App\Identity\Application\Exception\MagicLinkExpiredException;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class MagicLinkAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly VerifyMagicLinkHandler $verifyHandler,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'auth_verify';
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->attributes->get('token', '');

        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Brak tokenu uwierzytelniajacego.');
        }

        try {
            $user = ($this->verifyHandler)(new VerifyMagicLink($token));
        } catch (MagicLinkExpiredException) {
            throw new CustomUserMessageAuthenticationException('Link logowania wygasl. Popros o nowy.');
        } catch (MagicLinkInvalidException) {
            throw new CustomUserMessageAuthenticationException('Link logowania jest nieprawidlowy lub zostal juz uzyty.');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->email()),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('dashboard_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        \assert($session instanceof Session);
        $session->getFlashBag()->add('error', $exception->getMessageKey());

        return new RedirectResponse($this->urlGenerator->generate('auth_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('auth_login'));
    }
}
