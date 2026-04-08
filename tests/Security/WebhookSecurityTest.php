<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P2-111 — Webhook unsigned payload must return 400.
 *
 * The BillingController delegates signature verification to PaymentGatewayPort.
 * When the port returns null (invalid/missing signature) the controller responds
 * with HTTP 400.  This test confirms the security contract from the Security
 * test suite so it is visible when running --group=security.
 */
#[Group('security')]
final class WebhookSecurityTest extends WebTestCase
{
    /**
     * POST /billing/webhook with a non-empty body but no Stripe-Signature header
     * must return 400 (not 200 or 500).
     */
    public function testWebhookWithoutSignatureHeaderReturns400(): void
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
            '{"type":"checkout.session.completed","id":"evt_test_unsigned"}',
        );

        self::assertResponseStatusCodeSame(400);

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('error', $body);
        self::assertStringContainsString('signature', mb_strtolower((string) $body['error']));
    }

    /**
     * Stripe-Signature header present but containing garbage must also return 400.
     *
     * Ensures the signature verifier actively validates the value rather than
     * merely checking header presence.
     */
    public function testWebhookWithInvalidSignatureReturns400(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/billing/webhook',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=invalid,v1=garbage',
            ],
            '{"type":"checkout.session.completed","id":"evt_test_bad_sig"}',
        );

        self::assertResponseStatusCodeSame(400);
    }
}
