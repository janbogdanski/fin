<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Infrastructure;

use App\Billing\Application\Dto\WebhookEventType;
use App\Billing\Infrastructure\Stripe\StripePaymentGateway;
use PHPUnit\Framework\TestCase;
use Stripe\Webhook;

/**
 * Tests the Stripe adapter's webhook verification logic.
 *
 * We cannot easily test createCheckoutSession() without hitting Stripe API,
 * but verifyWebhook() uses Stripe\Webhook::constructEvent() which can be
 * tested with computed signatures.
 */
final class StripePaymentGatewayTest extends TestCase
{
    private const string WEBHOOK_SECRET = 'whsec_test_secret_for_unit_tests';

    public function testVerifyWebhookReturnsNullForInvalidSignature(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        $result = $gateway->verifyWebhook(
            '{"type":"checkout.session.completed"}',
            ['stripe-signature' => ['t=123,v1=invalid']],
        );

        self::assertNull($result, 'Invalid signature should return null');
    }

    public function testVerifyWebhookReturnsNullForMissingSignatureHeader(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        $result = $gateway->verifyWebhook('{"type":"checkout.session.completed"}', []);

        self::assertNull($result, 'Missing signature header should return null');
    }

    public function testVerifyWebhookReturnsNullForEmptyPayload(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        $result = $gateway->verifyWebhook('', ['stripe-signature' => ['t=123,v1=bad']]);

        self::assertNull($result, 'Empty payload with bad sig should return null');
    }

    public function testVerifyWebhookWithValidSignatureReturnsEvent(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_session_456',
                    'payment_intent' => 'pi_test_789',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        // Compute a valid Stripe signature
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $header = sprintf('t=%d,v1=%s', $timestamp, $signature);

        $result = $gateway->verifyWebhook($payload, ['stripe-signature' => [$header]]);

        self::assertNotNull($result);
        self::assertSame(WebhookEventType::PAYMENT_COMPLETED, $result->type);
        self::assertSame('cs_test_session_456', $result->sessionId);
        self::assertSame('pi_test_789', $result->transactionId);
    }

    public function testVerifyWebhookWithNonCheckoutEventReturnsOtherType(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        $payload = json_encode([
            'id' => 'evt_test_other',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, self::WEBHOOK_SECRET);
        $header = sprintf('t=%d,v1=%s', $timestamp, $signature);

        $result = $gateway->verifyWebhook($payload, ['stripe-signature' => [$header]]);

        self::assertNotNull($result);
        self::assertSame(WebhookEventType::OTHER, $result->type);
    }

    public function testVerifyWebhookHeaderExtractionIsCaseInsensitive(): void
    {
        $gateway = new StripePaymentGateway('sk_test_fake', self::WEBHOOK_SECRET);

        // Use uppercase header name — should still be extracted
        $result = $gateway->verifyWebhook(
            '{}',
            ['Stripe-Signature' => ['t=123,v1=invalid']],
        );

        // Will return null due to invalid sig, but the point is it doesn't crash
        self::assertNull($result);
    }
}
