<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for integration tests that require an authenticated user.
 *
 * Creates a real User entity in the database so that Symfony's
 * UserProvider::refreshUser() can load it on subsequent requests
 * (e.g., after followRedirect()).
 *
 * Subclasses should call createAuthenticatedClient() instead of
 * self::createClient() + loginUser().
 */
abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected const string TEST_USER_ID = '00000000-0000-0000-0000-000000000001';

    protected const string TEST_USER_EMAIL = 'test@example.com';

    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = self::createClient();
        $this->ensureTestUserExists();

        $securityUser = new SecurityUser(self::TEST_USER_ID, self::TEST_USER_EMAIL);
        $client->loginUser($securityUser);

        return $client;
    }

    private function ensureTestUserExists(): void
    {
        $container = self::getContainer();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get(\Doctrine\DBAL\Connection::class);

        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            [
                'id' => self::TEST_USER_ID,
            ],
        );

        if ((int) $exists > 0) {
            return;
        }

        $connection->insert('users', [
            'id' => self::TEST_USER_ID,
            'email' => self::TEST_USER_EMAIL,
            'created_at' => (new \DateTimeImmutable('2026-01-15 10:00:00'))->format('Y-m-d H:i:s'),
            'referral_code' => 'TEST-' . substr(self::TEST_USER_ID, 0, 8),
            'bonus_transactions' => 0,
        ]);
    }
}
