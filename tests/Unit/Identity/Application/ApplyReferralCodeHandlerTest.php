<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\ApplyReferralCode;
use App\Identity\Application\Command\ApplyReferralCodeHandler;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ApplyReferralCodeHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private ApplyReferralCodeHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('transactional')
            ->willReturnCallback(static fn (callable $cb) => $cb());
        $this->handler = new ApplyReferralCodeHandler($this->userRepository);
    }

    public function testValidReferralCodeLinksUsersAndAddsBonuses(): void
    {
        $referrer = User::register(UserId::fromString('019746a0-1234-7000-8000-000000000001'), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::fromString('019746a0-5678-7000-8000-000000000002'), 'new@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findByReferralCodeForUpdate')
            ->with('TAXPILOT-019746')
            ->willReturn($referrer);

        $this->userRepository->method('findByIdForUpdate')
            ->with($referee->id())
            ->willReturn($referee);

        $this->userRepository->expects(self::once())->method('flush');

        ($this->handler)(new ApplyReferralCode(
            refereeUserId: $referee->id()->toString(),
            referralCode: 'TAXPILOT-019746',
        ));

        self::assertSame('TAXPILOT-019746', $referee->referredBy());
        self::assertSame(10, $referee->bonusTransactions());
        self::assertSame(20, $referrer->bonusTransactions());
    }

    public function testInvalidReferralCodeThrows(): void
    {
        $referee = User::register(UserId::fromString('019746a0-5678-7000-8000-000000000002'), 'new@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findByReferralCodeForUpdate')
            ->with('TAXPILOT-INVALID')
            ->willReturn(null);

        $this->userRepository->method('findByIdForUpdate')
            ->willReturn($referee);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid referral code');

        ($this->handler)(new ApplyReferralCode(
            refereeUserId: $referee->id()->toString(),
            referralCode: 'TAXPILOT-INVALID',
        ));
    }

    public function testSelfReferralIsBlocked(): void
    {
        $user = User::register(UserId::fromString('019746a0-1234-7000-8000-000000000001'), 'jan@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findByReferralCodeForUpdate')
            ->with($user->referralCode())
            ->willReturn($user);

        $this->userRepository->method('findByIdForUpdate')
            ->willReturn($user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot refer yourself');

        ($this->handler)(new ApplyReferralCode(
            refereeUserId: $user->id()->toString(),
            referralCode: $user->referralCode(),
        ));
    }

    public function testRefereeNotFoundThrows(): void
    {
        $this->userRepository->method('findByIdForUpdate')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found');

        ($this->handler)(new ApplyReferralCode(
            refereeUserId: '019746a0-9999-7000-8000-000000000099',
            referralCode: 'TAXPILOT-019746',
        ));
    }

    public function testReferralAlreadyAppliedThrows(): void
    {
        $referrer = User::register(UserId::fromString('019746a0-1234-7000-8000-000000000001'), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::fromString('019746a0-5678-7000-8000-000000000002'), 'new@example.com', new \DateTimeImmutable());

        // Apply referral once so the guard is triggered on second call
        $referee->applyReferral($referrer);

        $this->userRepository->method('findByIdForUpdate')
            ->willReturn($referee);

        $anotherReferrer = User::register(UserId::fromString('019746a0-9999-7000-8000-000000000003'), 'other@example.com', new \DateTimeImmutable());

        $this->userRepository->method('findByReferralCodeForUpdate')
            ->willReturn($anotherReferrer);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Referral code already applied');

        ($this->handler)(new ApplyReferralCode(
            refereeUserId: $referee->id()->toString(),
            referralCode: $anotherReferrer->referralCode(),
        ));
    }
}
