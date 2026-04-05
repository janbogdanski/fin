<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\UserProvider;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserProviderTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private UserProvider $provider;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->provider = new UserProvider($this->userRepository);
    }

    public function testSupportsClassReturnsTrueForSecurityUser(): void
    {
        self::assertTrue($this->provider->supportsClass(SecurityUser::class));
    }

    public function testSupportsClassReturnsFalseForOtherClass(): void
    {
        self::assertFalse($this->provider->supportsClass(UserInterface::class));
        self::assertFalse($this->provider->supportsClass(\stdClass::class));
    }

    public function testLoadUserByIdentifierThrowsUserNotFoundWhenEmailNotFound(): void
    {
        $this->userRepository->method('findByEmail')
            ->with('notfound@example.com')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier('notfound@example.com');
    }

    public function testLoadUserByIdentifierReturnsSecurityUserWhenFound(): void
    {
        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $domainUser = User::register($userId, 'jan@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')
            ->with('jan@example.com')
            ->willReturn($domainUser);

        $securityUser = $this->provider->loadUserByIdentifier('jan@example.com');

        self::assertInstanceOf(SecurityUser::class, $securityUser);
        self::assertSame('jan@example.com', $securityUser->getUserIdentifier());
        self::assertSame('019746a0-1234-7000-8000-000000000001', $securityUser->id());
    }

    public function testRefreshUserThrowsForUnexpectedType(): void
    {
        $unexpectedUser = $this->createMock(UserInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unexpected user type:');

        $this->provider->refreshUser($unexpectedUser);
    }
}
