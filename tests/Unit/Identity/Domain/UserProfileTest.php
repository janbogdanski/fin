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
        self::assertNull($user->pesel());
        self::assertNull($user->firstName());
        self::assertNull($user->lastName());
        self::assertFalse($user->hasCompleteProfile());
    }

    public function testUpdateProfileWithValidNip(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', null, 'Jan', 'Kowalski');

        self::assertSame('5260000005', $user->nip());
        self::assertNull($user->pesel());
        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
        self::assertTrue($user->hasCompleteProfile());
    }

    public function testUpdateProfileWithValidPesel(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        // PESEL: 90090515836 — valid checksum
        $user->updateProfile(null, '90090515836', 'Jan', 'Kowalski');

        self::assertNull($user->nip());
        self::assertSame('90090515836', $user->pesel());
        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
        self::assertTrue($user->hasCompleteProfile());
    }

    public function testUpdateProfileTrimsWhitespace(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', null, '  Jan  ', '  Kowalski  ');

        self::assertSame('Jan', $user->firstName());
        self::assertSame('Kowalski', $user->lastName());
    }

    public function testUpdateProfileRejectsInvalidNipCheckDigit(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NIP check digit');

        $user->updateProfile('1234567890', null, 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsNipWrongLength(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NIP must be exactly 10 digits');

        $user->updateProfile('123', null, 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsInvalidPeselWrongLength(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PESEL must be exactly 11 digits');

        $user->updateProfile(null, '1234567890', 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsInvalidPeselChecksum(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PESEL check digit');

        $user->updateProfile(null, '90090515835', 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsBothNipAndPesel(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide either NIP or PESEL, not both');

        $user->updateProfile('5260000005', '90090515836', 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsNeitherNipNorPesel(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either NIP or PESEL must be provided');

        $user->updateProfile(null, null, 'Jan', 'Kowalski');
    }

    public function testUpdateProfileRejectsEmptyFirstName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $user->updateProfile('5260000005', null, '', 'Kowalski');
    }

    public function testUpdateProfileRejectsWhitespaceOnlyFirstName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $user->updateProfile('5260000005', null, '   ', 'Kowalski');
    }

    public function testUpdateProfileRejectsEmptyLastName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $user->updateProfile('5260000005', null, 'Jan', '');
    }

    public function testUpdateProfileRejectsWhitespaceOnlyLastName(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $user->updateProfile('5260000005', null, 'Jan', '   ');
    }

    public function testUpdateProfileCanBeCalledMultipleTimesWithNip(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', null, 'Jan', 'Kowalski');
        $user->updateProfile('7680000007', null, 'Anna', 'Nowak');

        self::assertSame('7680000007', $user->nip());
        self::assertNull($user->pesel());
        self::assertSame('Anna', $user->firstName());
        self::assertSame('Nowak', $user->lastName());
    }

    public function testUpdateProfileCanSwitchFromNipToPesel(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $user->updateProfile('5260000005', null, 'Jan', 'Kowalski');
        $user->updateProfile(null, '90090515836', 'Jan', 'Kowalski');

        self::assertNull($user->nip());
        self::assertSame('90090515836', $user->pesel());
    }

    public function testHasCompleteProfileReturnsFalseWhenPartial(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertFalse($user->hasCompleteProfile());
    }

    public function testHasCompleteProfileTrueWithPesel(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $user->updateProfile(null, '90090515836', 'Jan', 'Kowalski');

        self::assertTrue($user->hasCompleteProfile());
    }
}
