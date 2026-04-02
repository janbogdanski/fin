<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure;

use App\Identity\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    public function testInitialsFromProfileFirstAndLastName(): void
    {
        $user = new SecurityUser('id-1', 'anna@example.com', 'Anna', 'Kowalska');

        self::assertSame('AK', $user->initials());
    }

    public function testInitialsFromEmailWhenNoProfile(): void
    {
        $user = new SecurityUser('id-2', 'jan@example.com');

        self::assertSame('J', $user->initials());
    }

    public function testInitialsFromEmailWhenOnlyFirstNameSet(): void
    {
        $user = new SecurityUser('id-3', 'tom@example.com', 'Tomasz', null);

        self::assertSame('T', $user->initials());
    }

    public function testInitialsAreUppercased(): void
    {
        $user = new SecurityUser('id-4', 'anna@example.com', 'anna', 'kowalska');

        self::assertSame('AK', $user->initials());
    }

    public function testEmailMethodReturnsEmail(): void
    {
        $user = new SecurityUser('id-5', 'test@example.com');

        self::assertSame('test@example.com', $user->email());
    }
}
