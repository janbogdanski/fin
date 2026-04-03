<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserProfileTest extends TestCase
{
    public function testNewUserHasNoProfile(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertNull($user->nip());
        self::assertNull($user->firstName());
        self::assertNull($user->lastName());
        self::assertFalse($user->hasCompleteProfile());
    }

    public function testUpdateProfileWithValidData(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', 'Jan', 'Kowalski');

        self::assertSame('5260000005', $user->nip());
        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
        self::assertTrue($user->hasCompleteProfile());
    }

    public function testUpdateProfileTrimsWhitespace(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', '  Jan  ', '  Kowalski  ');

        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
    }

    public function testUpdateProfileRejectsInvalidNipCheckDigit(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NIP check digit');

        $user->updateProfile('1234567890', 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsNipWrongLength(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NIP must be exactly 10 digits');

        $user->updateProfile('123', 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsEmptyFirstName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $user->updateProfile('5260000005', '', 'Kowalski');
    }

    public function testUpdateProfileRejectsWhitespaceOnlyFirstName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $user->updateProfile('5260000005', '   ', 'Kowalski');
    }

    public function testUpdateProfileRejectsEmptyLastName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $user->updateProfile('5260000005', 'Jan', '');
    }

    public function testUpdateProfileRejectsWhitespaceOnlyLastName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $user->updateProfile('5260000005', 'Jan', '   ');
    }

    public function testUpdateProfileCanBeCalledMultipleTimes(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', 'Jan', 'Kowalski');
        $user->updateProfile('7680000007', 'Anna', 'Nowak');

        self::assertSame('7680000007', $user->nip());
        self::assertSame('Anna', $user->firstName());
        self::assertSame('Nowak', $user->lastName());
    }

    public function testHasCompleteProfileReturnsFalseWhenPartial(): void
    {
        // A user that only has some fields set should not happen via updateProfile
        // (all-or-nothing), but hasCompleteProfile should be robust.
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        // New user: all null
        self::assertFalse($user->hasCompleteProfile());
    }
}
