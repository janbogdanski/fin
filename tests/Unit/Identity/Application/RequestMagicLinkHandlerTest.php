<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\RequestMagicLink;
use App\Identity\Application\Command\RequestMagicLinkHandler;
use App\Identity\Application\Port\MagicLinkMailerPort;
use App\Identity\Application\Port\MagicLinkTokenGeneratorPort;
use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestMagicLinkHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private MagicLinkTokenGeneratorPort&MockObject $tokenGenerator;

    private MagicLinkMailerPort&MockObject $mailer;

    private RequestMagicLinkHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(MagicLinkTokenGeneratorPort::class);
        $this->mailer = $this->createMock(MagicLinkMailerPort::class);

        $this->handler = new RequestMagicLinkHandler(
            $this->userRepository,
            $this->tokenGenerator,
            $this->mailer,
        );
    }

    public function testGeneratesTokenAndSendsEmailForExistingUser(): void
    {
        $email = 'jan@example.com';
        $user = User::register(UserId::generate(), $email, new \DateTimeImmutable());
        $token = MagicLinkToken::create('signed-token-value', new \DateTimeImmutable('+15 minutes'));

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->tokenGenerator
            ->expects(self::once())
            ->method('generate')
            ->with($user)
            ->willReturn($token);

        $this->userRepository
            ->expects(self::once())
            ->method('save')
            ->with($user);

        $this->userRepository
            ->expects(self::once())
            ->method('flush');

        $this->mailer
            ->expects(self::once())
            ->method('sendMagicLink')
            ->with($email, $token);

        ($this->handler)(new RequestMagicLink($email));
    }

    public function testSilentlyIgnoresUnknownEmail(): void
    {
        $this->userRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->tokenGenerator
            ->expects(self::never())
            ->method('generate');

        $this->userRepository
            ->expects(self::never())
            ->method('flush');

        $this->mailer
            ->expects(self::never())
            ->method('sendMagicLink');

        ($this->handler)(new RequestMagicLink('unknown@example.com'));
    }
}
