<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;

final class UserMother
{
    /**
     * A standard user with a valid email and generated ID.
     */
    public static function standard(
        ?UserId $id = null,
        string $email = 'jan@example.com',
        ?\DateTimeImmutable $createdAt = null,
    ): User {
        return User::register(
            id: $id ?? UserId::generate(),
            email: $email,
            createdAt: $createdAt ?? new \DateTimeImmutable('2025-01-15 10:00:00'),
        );
    }

    /**
     * A user with a complete NIP profile (valid NIP with correct check digit).
     * NIP 5260000005 — well-known test NIP (Urzad Skarbowy Warszawa). Fictional for testing.
     */
    public static function withProfile(
        ?string $nip = '5260000005',
        string $firstName = 'Jan',
        string $lastName = 'Kowalski',
        ?UserId $id = null,
        ?string $pesel = null,
    ): User {
        $user = self::standard(id: $id);
        $user->updateProfile($nip, $pesel, $firstName, $lastName);

        return $user;
    }

    /**
     * A user with a complete PESEL profile.
     * PESEL 90090515836 — valid test PESEL.
     */
    public static function withPeselProfile(
        string $pesel = '90090515836',
        string $firstName = 'Jan',
        string $lastName = 'Kowalski',
        ?UserId $id = null,
    ): User {
        $user = self::standard(id: $id);
        $user->updateProfile(null, $pesel, $firstName, $lastName);

        return $user;
    }

    /**
     * A user without NIP or PESEL — has not completed their profile.
     */
    public static function withoutNIP(?UserId $id = null): User
    {
        return self::standard(id: $id);
    }
}
