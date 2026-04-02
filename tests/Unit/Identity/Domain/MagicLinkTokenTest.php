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

    public function testEmptyTokenThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MagicLinkToken::create('', new \DateTimeImmutable('+15 minutes'));
    }
}
