<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\RequestMagicLink;
use App\Identity\Application\Command\RequestMagicLinkHandler;
use App\Identity\Application\Command\VerifyMagicLink;
use App\Identity\Application\Command\VerifyMagicLinkHandler;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use App\Tests\Factory\UserMother;
use App\Tests\InMemory\InMemoryMagicLinkMailer;
use App\Tests\InMemory\InMemoryMagicLinkTokenGenerator;
use App\Tests\InMemory\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MagicLinkAuthenticationProcessTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryMagicLinkMailer $mailer;

    private InMemoryMagicLinkTokenGenerator $tokenGenerator;

    private RequestMagicLinkHandler $requestMagicLink;

    private VerifyMagicLinkHandler $verifyMagicLink;

    protected function setUp(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-04-08 10:15:00');

        $this->users = new InMemoryUserRepository();
        $this->mailer = new InMemoryMagicLinkMailer();
        $this->tokenGenerator = new InMemoryMagicLinkTokenGenerator('signed-token-123', $expiresAt);
        $this->requestMagicLink = new RequestMagicLinkHandler(
            $this->users,
            $this->tokenGenerator,
            $this->mailer,
        );
        $this->verifyMagicLink = new VerifyMagicLinkHandler(
            $this->users,
            new MockClock(new \DateTimeImmutable('2026-04-08 10:00:00')),
        );
    }

    public function testKnownUserCanRequestAndConsumeSingleUseMagicLink(): void
    {
        $user = UserMother::standard(email: 'login@example.com');
        $this->users->save($user);

        ($this->requestMagicLink)(new RequestMagicLink('login@example.com'));

        self::assertSame(1, $this->mailer->sentCount());
        self::assertSame('login@example.com', $this->mailer->lastEmail());
        self::assertNotNull($this->users->findByMagicLinkToken($this->tokenGenerator->rawToken()));

        $authenticated = ($this->verifyMagicLink)(new VerifyMagicLink($this->tokenGenerator->rawToken()));

        self::assertTrue($authenticated->id()->equals($user->id()));
        self::assertNull($authenticated->magicLinkToken());

        $this->expectException(MagicLinkInvalidException::class);

        ($this->verifyMagicLink)(new VerifyMagicLink($this->tokenGenerator->rawToken()));
    }

    public function testUnknownEmailDoesNotPersistTokenOrSendMail(): void
    {
        ($this->requestMagicLink)(new RequestMagicLink('missing@example.com'));

        self::assertSame(0, $this->mailer->sentCount());
        self::assertNull($this->users->findByMagicLinkToken($this->tokenGenerator->rawToken()));
    }
}
