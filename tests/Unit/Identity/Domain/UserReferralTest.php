<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserReferralTest extends TestCase
{
    public function testNewUserGetsReferralCodeOnRegistration(): void
    {
        $id = UserId::fromString('019746a0-1234-7000-8000-000000000001');
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());

        self::assertSame('TAXPILOT-019746', $user->referralCode());
    }

    public function testNewUserHasZeroBonusTransactions(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertSame(0, $user->bonusTransactions());
    }

    public function testNewUserHasNoReferrer(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertNull($user->referredBy());
    }

    public function testApplyReferralLinksSetsReferredBy(): void
    {
        $referrer = User::register(UserId::generate(), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::generate(), 'new@example.com', new \DateTimeImmutable());

        $referee->applyReferral($referrer);

        self::assertSame($referrer->referralCode(), $referee->referredBy());
    }

    public function testApplyReferralGivesReferrerTwentyBonusTransactions(): void
    {
        $referrer = User::register(UserId::generate(), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::generate(), 'new@example.com', new \DateTimeImmutable());

        $referee->applyReferral($referrer);

        self::assertSame(20, $referrer->bonusTransactions());
    }

    public function testApplyReferralGivesRefereeTenBonusTransactions(): void
    {
        $referrer = User::register(UserId::generate(), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::generate(), 'new@example.com', new \DateTimeImmutable());

        $referee->applyReferral($referrer);

        self::assertSame(10, $referee->bonusTransactions());
    }

    public function testSelfReferralIsBlocked(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot refer yourself');

        $user->applyReferral($user);
    }

    public function testCannotApplyReferralTwice(): void
    {
        $referrer = User::register(UserId::generate(), 'referrer@example.com', new \DateTimeImmutable());
        $referee = User::register(UserId::generate(), 'new@example.com', new \DateTimeImmutable());

        $referee->applyReferral($referrer);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Referral code already applied');

        $referee->applyReferral($referrer);
    }

    public function testReferrerBonusCappedAtTwoHundred(): void
    {
        $referrer = User::register(UserId::generate(), 'referrer@example.com', new \DateTimeImmutable());

        // Each referral gives referrer +20, so 10 referrals = 200 (cap)
        for ($i = 0; $i < 10; ++$i) {
            $referee = User::register(UserId::generate(), "user{$i}@example.com", new \DateTimeImmutable());
            $referee->applyReferral($referrer);
        }

        self::assertSame(200, $referrer->bonusTransactions());

        // 11th referral should not exceed cap
        $extra = User::register(UserId::generate(), 'extra@example.com', new \DateTimeImmutable());
        $extra->applyReferral($referrer);

        self::assertSame(200, $referrer->bonusTransactions());
    }

    public function testReferralCodeIsDeterministicBasedOnUserId(): void
    {
        $id = UserId::fromString('019746a0-abcd-7000-8000-000000000001');
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());

        self::assertSame('TAXPILOT-019746', $user->referralCode());
    }
}
