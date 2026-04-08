<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Identity\Application\Port\MagicLinkMailerPort;
use App\Identity\Domain\Model\MagicLinkToken;

final class InMemoryMagicLinkMailer implements MagicLinkMailerPort
{
    /**
     * @var list<array{email: string, token: MagicLinkToken}>
     */
    private array $sentMessages = [];

    public function sendMagicLink(string $email, MagicLinkToken $token): void
    {
        $this->sentMessages[] = [
            'email' => $email,
            'token' => $token,
        ];
    }

    public function sentCount(): int
    {
        return count($this->sentMessages);
    }

    public function lastEmail(): ?string
    {
        if ($this->sentMessages === []) {
            return null;
        }

        return $this->sentMessages[array_key_last($this->sentMessages)]['email'];
    }

    public function lastToken(): ?MagicLinkToken
    {
        if ($this->sentMessages === []) {
            return null;
        }

        return $this->sentMessages[array_key_last($this->sentMessages)]['token'];
    }
}
