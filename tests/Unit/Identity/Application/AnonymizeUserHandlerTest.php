<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\AnonymizeUser;
use App\Identity\Application\Command\AnonymizeUserHandler;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnonymizeUserHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private GdprDataErasurePort&MockObject $brokerFileErasure;

    private AnonymizeUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->brokerFileErasure = $this->createMock(GdprDataErasurePort::class);
        $this->handler = new AnonymizeUserHandler($this->userRepository, $this->brokerFileErasure);
    }

    public function testThrowsDomainExceptionWhenUserNotFound(): void
    {
        $this->stubTransactional();
        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');

        $this->userRepository->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found.');

        ($this->handler)(new AnonymizeUser($userId));
    }

    public function testAnonymizesUserAndFlushesInsideTransaction(): void
    {
        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $user = User::register($userId, 'jan@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);

        // handler loads user first, then wraps deleteByUser+anonymize+flush in a single transaction
        // for atomic GDPR art. 17 erasure — both tables commit or roll back together.
        $this->userRepository->expects(self::once())->method('transactional')
            ->willReturnCallback(static fn (callable $cb) => $cb());

        $this->brokerFileErasure->expects(self::once())->method('deleteByUser')
            ->with(self::equalTo($userId));

        $this->userRepository->expects(self::once())->method('anonymizeUser')
            ->with(self::equalTo($userId), self::isInstanceOf(\DateTimeImmutable::class));

        $this->userRepository->expects(self::once())->method('flush');

        ($this->handler)(new AnonymizeUser($userId));
    }

    /**
     * Idempotency: calling the handler on an already-anonymized user must throw.
     * The domain model protects against double-anonymization via DomainException.
     */
    public function testThrowsDomainExceptionWhenUserAlreadyAnonymized(): void
    {
        $this->stubTransactional();
        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $user = User::register($userId, 'jan@example.com', new \DateTimeImmutable());
        $user->anonymize(new \DateTimeImmutable());

        $this->userRepository->method('findById')->willReturn($user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is already anonymized');

        ($this->handler)(new AnonymizeUser($userId));
    }

    public function testAnonymizedUserHasCorrectTimestamp(): void
    {
        $this->stubTransactional();
        $userId = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $user = User::register($userId, 'jan@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findById')
            ->willReturn($user);

        $capturedTimestamp = null;
        $this->userRepository->method('anonymizeUser')
            ->willReturnCallback(static function (UserId $id, \DateTimeImmutable $now) use (&$capturedTimestamp): void {
                $capturedTimestamp = $now;
            });

        $before = new \DateTimeImmutable();
        ($this->handler)(new AnonymizeUser($userId));
        $after = new \DateTimeImmutable();

        self::assertInstanceOf(\DateTimeImmutable::class, $capturedTimestamp);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $capturedTimestamp->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $capturedTimestamp->getTimestamp());
    }

    private function stubTransactional(): void
    {
        $this->userRepository->method('transactional')
            ->willReturnCallback(static fn (callable $cb) => $cb());
    }
}
