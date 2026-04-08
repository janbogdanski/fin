<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Security tests for the magic link authentication flow.
 *
 * Copied (not moved) from tests/E2E/MagicLinkVerifyFlowTest.php so that
 * authentication regression paths (expired/invalid token) run in the default
 * integration pipeline. The @group e2e suite is excluded by default, meaning
 * a regression where an expired token is accepted would go undetected without
 * these tests here.
 *
 * Also covers session fixation prevention: verifies that a successful magic
 * link authentication redirects to /dashboard, confirming onAuthenticationSuccess
 * ran (which calls migrate(true) per MagicLinkAuthenticator line 62).
 *
 * @group security
 */
final class MagicLinkSecurityTest extends WebTestCase
{
    private const string USER_ID = '00000000-0000-0000-0002-000000000001';

    private const string USER_EMAIL = 'magic-link-security@example.com';

    private const string REFERRAL_CODE = 'SECURITY-ML1';

    public function testExpiredTokenRedirectsToLoginWithErrorFlash(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $rawToken = bin2hex(random_bytes(16));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s');

        $this->seedUserWithToken($connection, $hashedToken, $expiresAt);

        $client->request('GET', '/auth/verify/' . $rawToken);

        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        self::assertStringContainsString('wygasl', $crawler->text());
    }

    public function testInvalidTokenRedirectsToLoginWithErrorFlash(): void
    {
        $client = self::createClient();

        $client->request('GET', '/auth/verify/totally-invalid-nonexistent-token-security-test');

        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        self::assertStringContainsString('nieprawidlowy', mb_strtolower($crawler->text()));
    }

    /**
     * Session fixation prevention test.
     *
     * MagicLinkAuthenticator::onAuthenticationSuccess calls $request->getSession()->migrate(true)
     * before redirecting. This test verifies the successful authentication path — i.e., that
     * a valid token leads to a redirect to /dashboard, confirming onAuthenticationSuccess ran
     * (and therefore migrate(true) was called). A functional test cannot directly inspect
     * PHP session internals across the HTTP boundary, so we verify the observable outcome:
     * redirect to /dashboard with a successful follow-through.
     */
    public function testValidTokenSessionIsMigratedOnSuccessfulAuth(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $rawToken = bin2hex(random_bytes(16));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

        $this->seedUserWithToken($connection, $hashedToken, $expiresAt);

        // Capture the session ID before authentication
        $client->request('GET', '/login');
        $sessionIdBefore = $client->getRequest()->getSession()->getId();

        // Submit the magic link — triggers MagicLinkAuthenticator
        $client->request('GET', '/auth/verify/' . $rawToken);

        // onAuthenticationSuccess calls migrate(true) and redirects to dashboard
        self::assertResponseRedirects('/dashboard');

        // Capture session ID after authentication — must differ (migrate(true) regenerates it)
        $sessionIdAfter = $client->getRequest()->getSession()->getId();

        self::assertNotSame(
            $sessionIdBefore,
            $sessionIdAfter,
            'Session ID must change after successful authentication to prevent session fixation attacks.',
        );
    }

    private function seedUserWithToken(Connection $connection, string $hashedToken, string $expiresAt): void
    {
        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            [
                'id' => self::USER_ID,
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
                    'id' => self::USER_ID,
                ],
            );
        } else {
            $connection->insert('users', [
                'id' => self::USER_ID,
                'email' => self::USER_EMAIL,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'referral_code' => self::REFERRAL_CODE,
                'bonus_transactions' => 0,
                'login_token' => $hashedToken,
                'login_token_expires_at' => $expiresAt,
            ]);
        }
    }
}
