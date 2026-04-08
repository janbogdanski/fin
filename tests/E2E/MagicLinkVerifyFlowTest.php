<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E2E: User clicks magic link → gets logged in and lands on dashboard.
 *
 * Tests the full magic link verification flow: valid token → authenticated session.
 * Also covers the expired token and invalid token error paths.
 *
 * @group e2e
 */
final class MagicLinkVerifyFlowTest extends WebTestCase
{
    private const string VERIFY_USER_ID = '00000000-0000-0000-0001-000000000001';

    private const string VERIFY_USER_EMAIL = 'magic-link-e2e@example.com';

    public function testValidTokenRedirectsToDashboardAndLogsUserIn(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $rawToken = bin2hex(random_bytes(16));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

        $this->insertUserWithToken($connection, $hashedToken, $expiresAt);

        // Click magic link
        $client->request('GET', '/auth/verify/' . $rawToken);

        // Should redirect to dashboard
        self::assertResponseRedirects('/dashboard');

        // Follow redirect — user should now be authenticated
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testExpiredTokenRedirectsToLoginWithError(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $rawToken = bin2hex(random_bytes(16));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s');

        $this->insertUserWithToken($connection, $hashedToken, $expiresAt);

        $client->request('GET', '/auth/verify/' . $rawToken);

        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();
        self::assertStringContainsString('wygasl', $pageText);
    }

    public function testInvalidTokenRedirectsToLoginWithError(): void
    {
        $client = self::createClient();

        $client->request('GET', '/auth/verify/totally-invalid-nonexistent-token');

        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();
        self::assertStringContainsString('nieprawidlowy', mb_strtolower($pageText));
    }

    private function insertUserWithToken(Connection $connection, string $hashedToken, string $expiresAt): void
    {
        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            [
                'id' => self::VERIFY_USER_ID,
            ],
        );

        if ((int) $exists > 0) {
            $connection->update(
                'users',
                [
                    'login_token' => $hashedToken,
                    'login_token_expires_at' => $expiresAt,
                ],
                [
                    'id' => self::VERIFY_USER_ID,
                ],
            );
        } else {
            $connection->insert('users', [
                'id' => self::VERIFY_USER_ID,
                'email' => self::VERIFY_USER_EMAIL,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'referral_code' => 'MAGIC-TEST1',
                'bonus_transactions' => 0,
                'login_token' => $hashedToken,
                'login_token_expires_at' => $expiresAt,
            ]);
        }
    }
}
