<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\VerifyMagicLink;
use App\Identity\Application\Command\VerifyMagicLinkHandler;
use App\Identity\Application\Exception\MagicLinkExpiredException;
use App\Identity\Application\Exception\MagicLinkInvalidException;
use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class VerifyMagicLinkHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private ClockInterface&MockObject $clock;

    private VerifyMagicLinkHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2025-06-15 12:00:00'));

        // transactional() executes the callback immediately (simulates DB transaction)
        $this->userRepository
            ->method('transactional')
            ->willReturnCallback(static fn (callable $cb) => $cb());

        $this->handler = new VerifyMagicLinkHandler($this->userRepository, $this->clock);
    }

    public function testValidTokenAuthenticatesUser(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable('2025-06-15 11:00:00'));
        $token = MagicLinkToken::create('valid-token', new \DateTimeImmutable('2025-06-15 12:15:00'));
        $user->setMagicLinkToken($token);

        $this->userRepository
            ->method('findByMagicLinkToken')
            ->with('valid-token')
            ->willReturn($user);

        $this->userRepository
            ->expects(self::once())
            ->method('save')
            ->with($user);

        $this->userRepository
            ->expects(self::once())
            ->method('flush');

        $result = ($this->handler)(new VerifyMagicLink('valid-token'));

        self::assertSame($user, $result);
        self::assertNull($user->magicLinkToken(), 'Token should be invalidated after use');
    }

    public function testExpiredTokenThrowsException(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable('2025-06-15 11:00:00'));
        $token = MagicLinkToken::create('expired-token', new \DateTimeImmutable('2025-06-15 11:59:00'));
        $user->setMagicLinkToken($token);

        $this->userRepository
            ->method('findByMagicLinkToken')
            ->with('expired-token')
            ->willReturn($user);

        $this->userRepository
            ->expects(self::never())
            ->method('flush');

        $this->expectException(MagicLinkExpiredException::class);

        ($this->handler)(new VerifyMagicLink('expired-token'));
    }

    public function testUnknownTokenThrowsException(): void
    {
        $this->userRepository
            ->method('findByMagicLinkToken')
            ->willReturn(null);

        $this->userRepository
            ->expects(self::never())
            ->method('flush');

        $this->expectException(MagicLinkInvalidException::class);

        ($this->handler)(new VerifyMagicLink('nonexistent-token'));
    }

    public function testUsedTokenThrowsException(): void
    {
        // Token was already consumed (null on user)
        $this->userRepository
            ->method('findByMagicLinkToken')
            ->with('used-token')
            ->willReturn(null);

        $this->userRepository
            ->expects(self::never())
            ->method('flush');

        $this->expectException(MagicLinkInvalidException::class);

        ($this->handler)(new VerifyMagicLink('used-token'));
    }
}
