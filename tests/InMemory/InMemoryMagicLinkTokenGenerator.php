<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Identity\Application\Port\MagicLinkTokenGeneratorPort;
use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;

final readonly class InMemoryMagicLinkTokenGenerator implements MagicLinkTokenGeneratorPort
{
    public function __construct(
        private string $rawToken,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function generate(User $user): MagicLinkToken
    {
        return MagicLinkToken::create($this->rawToken, $this->expiresAt);
    }

    public function rawToken(): string
    {
        return $this->rawToken;
    }
}
