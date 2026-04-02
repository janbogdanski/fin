<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\MagicLinkToken;
use App\Identity\Domain\Model\User;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserMagicLinkTest extends TestCase
{
    public function testSetAndGetMagicLinkToken(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $token = MagicLinkToken::create('token123', new \DateTimeImmutable('+15 minutes'));

        $user->setMagicLinkToken($token);

        $retrieved = $user->magicLinkToken();
        self::assertNotNull($retrieved);
        self::assertSame($token->token(), $retrieved->token());
        self::assertEquals($token->expiresAt(), $retrieved->expiresAt());
    }

    public function testConsumeMagicLinkTokenNullifiesIt(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());
        $token = MagicLinkToken::create('token123', new \DateTimeImmutable('+15 minutes'));

        $user->setMagicLinkToken($token);
        $user->consumeMagicLinkToken();

        self::assertNull($user->magicLinkToken());
    }

    public function testNewUserHasNoToken(): void
    {
        $user = User::register(UserId::generate(), 'jan@example.com', new \DateTimeImmutable());

        self::assertNull($user->magicLinkToken());
    }
}
