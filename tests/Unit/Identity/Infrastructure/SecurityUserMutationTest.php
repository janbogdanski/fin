<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure;

use App\Identity\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for SecurityUser.
 *
 * Targets: getUserIdentifier, getRoles, id(), initials mb_substr/mb_strtoupper,
 * null-check LogicalAnd on firstName/lastName.
 */
final class SecurityUserMutationTest extends TestCase
{
    /**
     * Kills ArrayOneItem on getRoles.
     */
    public function testGetRolesReturnsExactlyRoleUser(): void
    {
        $user = new SecurityUser('id-1', 'anna@example.com');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    /**
     * Kills mutation on getUserIdentifier returning id instead of email.
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new SecurityUser('my-id', 'anna@example.com');

        self::assertSame('anna@example.com', $user->getUserIdentifier());
        self::assertSame('my-id', $user->id());
    }

    /**
     * Kills LogicalAnd mutant on initials:
     * if ($this->firstName !== null && $this->lastName !== null).
     * When only lastName is set, should fall back to email initial.
     */
    public function testInitialsFallsBackToEmailWhenOnlyLastNameSet(): void
    {
        $user = new SecurityUser('id-3', 'tom@example.com', null, 'Kowalski');

        self::assertSame('T', $user->initials());
    }

    /**
     * Kills mb_substr mutant: mb_substr($this->firstName, 0, 1) changed to mb_substr($this->firstName, 0, 2).
     * Also kills ConcatOperandRemoval.
     */
    public function testInitialsAreTwoCharsFromFirstAndLastName(): void
    {
        $user = new SecurityUser('id-4', 'anna@example.com', 'Anna', 'Kowalska');

        $initials = $user->initials();
        self::assertSame(2, mb_strlen($initials));
        self::assertSame('A', mb_substr($initials, 0, 1));
        self::assertSame('K', mb_substr($initials, 1, 1));
    }

    /**
     * Kills mb_strtoupper removal: initials should be uppercased.
     */
    public function testInitialsAreUppercasedForUnicodeNames(): void
    {
        $user = new SecurityUser('id-5', 'anna@example.com', 'ądam', 'żurek');

        self::assertSame('ĄŻ', $user->initials());
    }
}
