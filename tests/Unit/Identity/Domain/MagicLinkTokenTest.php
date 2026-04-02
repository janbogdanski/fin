<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\MagicLinkToken;
use PHPUnit\Framework\TestCase;

final class MagicLinkTokenTest extends TestCase
{
    public function testCreateReturnsToken(): void
    {
        $expiresAt = new \DateTimeImmutable('+15 minutes');
        $token = MagicLinkToken::create('abc123', $expiresAt);

        self::assertSame('abc123', $token->token());
        self::assertSame($expiresAt, $token->expiresAt());
    }

    public function testIsExpiredReturnsFalseForFutureExpiry(): void
    {
        $token = MagicLinkToken::create('abc', new \DateTimeImmutable('+15 minutes'));

        self::assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsTrueForPastExpiry(): void
    {
        $token = MagicLinkToken::create('abc', new \DateTimeImmutable('-1 second'));

        self::assertTrue($token->isExpired());
    }

    /**
     * Boundary: token is expired exactly AT expiresAt (inclusive >= comparison).
     * At the exact expiration moment, the token must be considered expired.
     */
    public function testIsExpiredAtExactExpiresAtBoundary(): void
    {
        $expiresAt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $token = MagicLinkToken::create('abc123', $expiresAt);

        // Exactly at expiresAt — must be expired (>= comparison)
        self::assertTrue($token->isExpired($expiresAt));
    }

    /**
     * One second before expiresAt — token must still be valid.
     */
    public function testIsNotExpiredOneSecondBeforeExpiresAt(): void
    {
        $expiresAt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $token = MagicLinkToken::create('abc123', $expiresAt);

        $oneSecondBefore = new \DateTimeImmutable('2025-06-15 11:59:59');
        self::assertFalse($token->isExpired($oneSecondBefore));
    }

    public function testEmptyTokenThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MagicLinkToken::create('', new \DateTimeImmutable('+15 minutes'));
    }
}
