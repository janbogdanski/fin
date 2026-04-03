<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for BillingController.
 *
 * - Webhook endpoint is PUBLIC_ACCESS (no auth required).
 * - Checkout endpoint requires authentication + CSRF.
 */
final class BillingControllerWebTest extends WebTestCase
{
    /**
     * POST /billing/webhook without a valid signature should return 400.
     *
     * The PaymentGatewayPort::verifyWebhook() returns null for invalid
     * signatures, which the controller maps to 400.
     */
    public function testWebhookWithoutSignatureReturns400(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/billing/webhook',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{"type":"checkout.session.completed"}',
        );

        self::assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $body = json_decode((string) $response->getContent(), true);

        self::assertIsArray($body);
        self::assertArrayHasKey('error', $body);
        self::assertStringContainsString('signature', mb_strtolower($body['error']));
    }

    public function testWebhookWithEmptyPayloadReturns400(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/billing/webhook',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '',
        );

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * POST /billing/checkout without authentication should redirect to login.
     */
    public function testCheckoutWithoutAuthRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('POST', '/billing/checkout', [
            'product_code' => 'STANDARD',
            'tax_year' => '2025',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/login',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    /**
     * POST /billing/checkout with auth but invalid CSRF should return 403.
     */
    public function testCheckoutWithInvalidCsrfReturns403(): void
    {
        $client = self::createClient();

        // Use the authenticated base class approach inline since we extend WebTestCase
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = self::getContainer()->get(\Doctrine\DBAL\Connection::class);
        $userId = '00000000-0000-0000-0000-000000000002';
        $email = 'billing-test@example.com';

        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            [
                'id' => $userId,
            ],
        );

        if ($exists === 0) {
            $connection->insert('users', [
                'id' => $userId,
                'email' => $email,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'referral_code' => 'TEST-BILLING1',
                'bonus_transactions' => 0,
            ]);
        }

        $securityUser = new \App\Identity\Infrastructure\Security\SecurityUser($userId, $email);
        $client->loginUser($securityUser);

        $client->request('POST', '/billing/checkout', [
            '_csrf_token' => 'invalid',
            'product_code' => 'STANDARD',
            'tax_year' => '2025',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
