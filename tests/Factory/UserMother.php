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
     */
    /**
     * NIP 5260000005 — well-known test NIP (Urząd Skarbowy Warszawa). Fictional for testing.
     */
    public static function withProfile(
        string $nip = '5260000005',
        string $firstName = 'Jan',
        string $lastName = 'Kowalski',
        ?UserId $id = null,
    ): User {
        $user = self::standard(id: $id);
        $user->updateProfile($nip, $firstName, $lastName);

        return $user;
    }

    /**
     * A user without NIP — has not completed their profile.
     */
    public static function withoutNIP(?UserId $id = null): User
    {
        return self::standard(id: $id);
    }
}
