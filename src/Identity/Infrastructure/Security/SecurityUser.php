<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony Security adapter — wraps domain User for the security layer.
 * Intentionally separate from the domain User entity.
 */
final readonly class SecurityUser implements UserInterface
{
    public function __construct(
        private string $id,
        private string $email,
        private ?string $firstName = null,
        private ?string $lastName = null,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // No credentials stored — magic link auth.
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    /**
     * Returns user initials for avatar display.
     * Profile with first+last name -> first letters of each (e.g. "AK").
     * No profile -> first letter of email uppercased (e.g. "J").
     */
    public function initials(): string
    {
        if ($this->firstName !== null && $this->lastName !== null) {
            return mb_strtoupper(mb_substr($this->firstName, 0, 1) . mb_substr($this->lastName, 0, 1));
        }

        return mb_strtoupper(mb_substr($this->email, 0, 1));
    }
}
