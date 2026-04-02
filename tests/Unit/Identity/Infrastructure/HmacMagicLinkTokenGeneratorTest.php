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
}
