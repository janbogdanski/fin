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
}
