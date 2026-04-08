<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;

trait SeedsDatabaseUser
{
    protected function seedUser(UserId $userId): void
    {
        /** @var Connection $conn */
        $conn = self::getContainer()->get(Connection::class);
        $conn->insert('users', [
            'id' => $userId->toString(),
            'email' => 'test-' . $userId->toString() . '@test.invalid',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'referral_code' => 'T' . strtoupper(substr(md5($userId->toString()), 0, 18)),
        ]);
    }
}
