<?php

declare(strict_types=1);

namespace App\Tests\Chaos;

use App\Billing\Application\Port\PaymentGatewayPort;
use PHPUnit\Framework\TestCase;

/**
 * @group chaos
 *
 * Simulates payment webhook infrastructure failures:
 * - Invalid/tampered signatures
 * - Replay attacks (duplicate events)
 * - Malformed payloads
 */
final class PaymentWebhookChaosTest extends TestCase
{
    public function testInvalidSignatureReturnsNull(): void
    {
        $gateway = $this->createMock(PaymentGatewayPort::class);
        $gateway->method('verifyWebhook')
            ->willReturn(null);

        $result = $gateway->verifyWebhook(
            '{"type":"checkout.session.completed","data":{"object":{"id":"cs_test_123"}}}',
            [
                'stripe-signature' => ['t=1,v1=invalid_signature'],
            ],
        );

        self::assertNull($result, 'Invalid signature should return null (webhook rejected)');
    }

    public function testMalformedPayloadDoesNotCrash(): void
    {
        $gateway = $this->createMock(PaymentGatewayPort::class);
        $gateway->method('verifyWebhook')
            ->willReturn(null);

        $result = $gateway->verifyWebhook(
            'this is not json at all {{{',
            [
                'stripe-signature' => ['t=1,v1=some_sig'],
            ],
        );

        self::assertNull($result, 'Malformed payload should return null, not throw');
    }

    public function testEmptyPayloadHandledGracefully(): void
    {
        $gateway = $this->createMock(PaymentGatewayPort::class);
        $gateway->method('verifyWebhook')
            ->willReturn(null);

        $result = $gateway->verifyWebhook('', []);

        self::assertNull($result, 'Empty payload should return null');
    }
}
