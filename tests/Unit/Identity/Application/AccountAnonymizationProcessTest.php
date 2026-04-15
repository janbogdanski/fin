<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\AnonymizeUser;
use App\Identity\Application\Command\AnonymizeUserHandler;
use App\Identity\Application\Command\RequestMagicLink;
use App\Identity\Application\Command\RequestMagicLinkHandler;
use App\Identity\Application\Command\VerifyMagicLink;
use App\Identity\Application\Command\VerifyMagicLinkHandler;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Tests\Factory\UserMother;
use App\Tests\InMemory\InMemoryMagicLinkMailer;
use App\Tests\InMemory\InMemoryMagicLinkTokenGenerator;
use App\Tests\InMemory\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AccountAnonymizationProcessTest extends TestCase
{
    private InMemoryUserRepository $users;

    private InMemoryMagicLinkMailer $mailer;

    private InMemoryMagicLinkTokenGenerator $tokenGenerator;

    private RequestMagicLinkHandler $requestMagicLink;

    private VerifyMagicLinkHandler $verifyMagicLink;

    private AnonymizeUserHandler $anonymizeUser;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->mailer = new InMemoryMagicLinkMailer();
        $this->tokenGenerator = new InMemoryMagicLinkTokenGenerator(
            'signed-token-before-deletion',
            new \DateTimeImmutable('2026-04-09 10:15:00'),
        );
        $this->requestMagicLink = new RequestMagicLinkHandler(
            $this->users,
            $this->tokenGenerator,
            $this->mailer,
        );
        $this->verifyMagicLink = new VerifyMagicLinkHandler(
            $this->users,
            new MockClock(new \DateTimeImmutable('2026-04-09 10:00:00')),
        );
        $noopAdapterRequestPort = new class() implements GdprDataErasurePort {
            public function deleteByUser(\App\Shared\Domain\ValueObject\UserId $userId): void
            {
            }
        };
        $this->anonymizeUser = new AnonymizeUserHandler($this->users, $noopAdapterRequestPort);
    }

    public function testAnonymizationRevokesExistingLoginVectorAndOldEmailStopsWorking(): void
    {
        $user = UserMother::standard(email: 'delete-me@example.com');
        $user->updateProfile('5260000005', 'Jan', 'Kowalski');
        $this->users->save($user);

        ($this->requestMagicLink)(new RequestMagicLink('delete-me@example.com'));

        self::assertSame(1, $this->mailer->sentCount());
        self::assertSame('delete-me@example.com', $this->mailer->lastEmail());
        self::assertNotNull($this->users->findByMagicLinkToken($this->tokenGenerator->rawToken()));

        ($this->anonymizeUser)(new AnonymizeUser($user->id()));

        $anonymizedUser = $this->users->findById($user->id());

        self::assertNotNull($anonymizedUser);
        self::assertTrue($anonymizedUser->isAnonymized());
        self::assertFalse($anonymizedUser->hasCompleteProfile());
        self::assertNull($anonymizedUser->magicLinkToken());
        self::assertNull($this->users->findByMagicLinkToken($this->tokenGenerator->rawToken()));
        self::assertNotSame('delete-me@example.com', $anonymizedUser->email());

        ($this->requestMagicLink)(new RequestMagicLink('delete-me@example.com'));

        self::assertSame(1, $this->mailer->sentCount());

        $this->expectException(MagicLinkInvalidException::class);

        ($this->verifyMagicLink)(new VerifyMagicLink($this->tokenGenerator->rawToken()));
    }
}
