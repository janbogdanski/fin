<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Application\Command\VerifyMagicLinkHandler;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\MagicLinkAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class MagicLinkAuthenticatorTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private MagicLinkAuthenticator $authenticator;

    protected function setUp(): void
    {
        // VerifyMagicLinkHandler is final readonly — construct with mocked ports
        $verifyHandler = new VerifyMagicLinkHandler(
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(ClockInterface::class),
        );

        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new MagicLinkAuthenticator(
            $verifyHandler,
            $this->urlGenerator,
        );
    }

    /**
     * Session fixation prevention: onAuthenticationSuccess MUST call migrate(true).
     *
     * This unit test complements MagicLinkSecurityTest (integration) by directly
     * asserting the method call on a mock session — not just observing side effects.
     */
    public function testOnAuthenticationSuccessCallsSessionMigrateWithDeleteOldSession(): void
    {
        $session = $this->createMock(SessionInterface::class);

        // Core assertion: migrate(true) — delete old session, regenerate ID
        $session->expects(self::once())
            ->method('migrate')
            ->with(true)
            ->willReturn(true);

        $request = Request::create('/auth/verify/sometoken');
        $request->setSession($session);

        $this->urlGenerator
            ->method('generate')
            ->with('dashboard_index')
            ->willReturn('/dashboard');

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertSame('/dashboard', $response->headers->get('Location'));
    }

    public function testSupportsReturnsTrueForAuthVerifyRoute(): void
    {
        $request = Request::create('/auth/verify/abc');
        $request->attributes->set('_route', 'auth_verify');

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testSupportsReturnsFalseForOtherRoute(): void
    {
        $request = Request::create('/login');
        $request->attributes->set('_route', 'auth_login');

        self::assertFalse($this->authenticator->supports($request));
    }
}
