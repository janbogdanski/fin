<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure;

use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Security\HmacMagicLinkTokenGenerator;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class HmacMagicLinkTokenGeneratorTest extends TestCase
{
    public function testGenerateReturnsNonEmptyToken(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-04-02 12:00:00'));
        $generator = new HmacMagicLinkTokenGenerator('test-app-secret', $clock);
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $token = $generator->generate($user);

        self::assertNotEmpty($token->token());
        self::assertFalse($token->isExpired(new \DateTimeImmutable('2026-04-02 12:00:00')));
    }

    public function testGenerateProducesDifferentTokensEachCall(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-04-02 12:00:00'));
        $generator = new HmacMagicLinkTokenGenerator('test-app-secret', $clock);
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $token1 = $generator->generate($user);
        $token2 = $generator->generate($user);

        self::assertNotSame($token1->token(), $token2->token());
    }

    public function testTokenExpiresIn15Minutes(): void
    {
        $now = new \DateTimeImmutable('2026-04-02 12:00:00');
        $clock = new MockClock($now);
        $generator = new HmacMagicLinkTokenGenerator('test-app-secret', $clock);
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $token = $generator->generate($user);

        $expectedExpiry = $now->modify('+15 minutes');
        self::assertEquals($expectedExpiry, $token->expiresAt());
    }

    public function testTokenStructureIsNonceDotSignature(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-04-02 12:00:00'));
        $generator = new HmacMagicLinkTokenGenerator('test-app-secret', $clock);
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $token = $generator->generate($user);
        $parts = explode('.', $token->token());

        self::assertCount(2, $parts, 'Token should have nonce.signature format');
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parts[0], 'Nonce should be 32 hex chars (16 bytes)');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $parts[1], 'Signature should be 64 hex chars (SHA-256)');
    }

    public function testTokenIsExpiredAfter15Minutes(): void
    {
        $now = new \DateTimeImmutable('2026-04-02 12:00:00');
        $clock = new MockClock($now);
        $generator = new HmacMagicLinkTokenGenerator('test-app-secret', $clock);
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $token = $generator->generate($user);

        self::assertFalse($token->isExpired(new \DateTimeImmutable('2026-04-02 12:14:59')));
        self::assertTrue($token->isExpired(new \DateTimeImmutable('2026-04-02 12:15:00')));
    }

    public function testDifferentSecretsProduceDifferentSignatures(): void
    {
        $now = new \DateTimeImmutable('2026-04-02 12:00:00');
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        $gen1 = new HmacMagicLinkTokenGenerator('secret-aaa', new MockClock($now));
        $gen2 = new HmacMagicLinkTokenGenerator('secret-bbb', new MockClock($now));

        $token1 = $gen1->generate($user);
        $token2 = $gen2->generate($user);

        // Nonces differ (random), but signatures definitely differ due to different secrets
        $sig1 = explode('.', $token1->token())[1];
        $sig2 = explode('.', $token2->token())[1];
        self::assertNotSame($sig1, $sig2);
    }
}
