<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\MagicLinkTokenGeneratorPort;
use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;
use Psr\Clock\ClockInterface;

/**
 * Generates HMAC-signed magic link tokens.
 *
 * Token = base64url(HMAC-SHA256(userId + nonce + expiresAt, APP_SECRET))
 * The nonce ensures uniqueness even for the same user and timestamp.
 */
final readonly class HmacMagicLinkTokenGenerator implements MagicLinkTokenGeneratorPort
{
    private const int TOKEN_LIFETIME_MINUTES = 15;

    public function __construct(
        private string $appSecret,
        private ClockInterface $clock,
    ) {
    }

    public function generate(User $user): MagicLinkToken
    {
        $now = $this->clock->now();
        $expiresAt = $now->modify(sprintf('+%d minutes', self::TOKEN_LIFETIME_MINUTES));

        $nonce = bin2hex(random_bytes(16));
        $payload = implode('|', [
            $user->id()->toString(),
            $nonce,
            $expiresAt->getTimestamp(),
        ]);

        $signature = hash_hmac('sha256', $payload, $this->appSecret);
        $token = $nonce . '.' . $signature;

        return MagicLinkToken::create($token, $expiresAt);
    }
}
