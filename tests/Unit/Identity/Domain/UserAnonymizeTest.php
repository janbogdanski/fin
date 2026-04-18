<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for User::anonymize() — GDPR art. 17 right to erasure.
 */
final class UserAnonymizeTest extends TestCase
{
    public function testNewUserIsNotAnonymized(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertFalse($user->isAnonymized());
        self::assertNull($user->anonymizedAt());
    }

    public function testAnonymizeMarksUserAsAnonymized(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $now = new \DateTimeImmutable('2026-04-05 12:00:00');

        $user->anonymize($now);

        self::assertTrue($user->isAnonymized());
        self::assertSame($now, $user->anonymizedAt());
    }

    public function testAnonymizeClearsProfilePii(): void
    {
        $id = UserId::generate();
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());
        $user->updateProfile('5260000005', null, 'Jan', 'Kowalski');

        $user->anonymize(new \DateTimeImmutable());

        self::assertNull($user->nip());
        self::assertNull($user->pesel());
        self::assertNull($user->firstName());
        self::assertNull($user->lastName());
    }

    public function testAnonymizeClearsPesel(): void
    {
        $id = UserId::generate();
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());
        $user->updateProfile(null, '90090515836', 'Jan', 'Kowalski');

        $user->anonymize(new \DateTimeImmutable());

        self::assertNull($user->pesel());
    }

    public function testAnonymizeClearsMagicLinkToken(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $token = \App\Identity\Domain\Model\MagicLinkToken::create(
            'raw-token',
            new \DateTimeImmutable('+15 minutes'),
        );
        $user->setMagicLinkToken($token);

        $user->anonymize(new \DateTimeImmutable());

        self::assertNull($user->magicLinkToken());
    }

    public function testAnonymizeReplacesEmailWithPlaceholder(): void
    {
        $id = UserId::generate();
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());

        $user->anonymize(new \DateTimeImmutable());

        self::assertStringStartsWith('deleted-', $user->email());
        self::assertStringEndsWith('@deleted.invalid', $user->email());
        self::assertStringContainsString($id->toString(), $user->email());
    }

    public function testAnonymizeScrubsReferralCode(): void
    {
        $id = UserId::generate();
        $user = User::register($id, 'jan@example.com', new \DateTimeImmutable());
        $originalCode = $user->referralCode();

        $user->anonymize(new \DateTimeImmutable());

        self::assertStringStartsWith('DELETED-', $user->referralCode());
        self::assertNotSame($originalCode, $user->referralCode());
    }

    public function testAnonymizeCannotBeCalledTwice(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $user->anonymize(new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is already anonymized');

        $user->anonymize(new \DateTimeImmutable());
    }

    public function testHasCompleteProfileReturnsFalseAfterAnonymize(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $user->updateProfile('5260000005', null, 'Jan', 'Kowalski');

        $user->anonymize(new \DateTimeImmutable());

        self::assertFalse($user->hasCompleteProfile());
    }
}
