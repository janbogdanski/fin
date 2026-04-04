<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Billing\Application\Dto\CheckoutSessionResult;
use App\Billing\Application\Dto\WebhookEvent;
use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

/**
 * In-memory stub for PaymentGatewayPort.
 *
 * Designed for integration tests that exercise the billing checkout flow
 * without a live Stripe connection.  Returns a predictable checkout URL
 * so the controller redirect can be asserted deterministically.
 */
final class InMemoryPaymentGatewayAdapter implements PaymentGatewayPort
{
    public const string FAKE_CHECKOUT_URL = 'https://checkout.stripe.com/fake-session-abc123';

    public const string FAKE_SESSION_ID = 'cs_test_fake_session_abc123';

    public function createCheckoutSession(
        UserId $userId,
        ProductCode $productCode,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult {
        return new CheckoutSessionResult(
            sessionId: self::FAKE_SESSION_ID,
            checkoutUrl: self::FAKE_CHECKOUT_URL,
        );
    }

    public function verifyWebhook(string $payload, array $headers): ?WebhookEvent
    {
        return null;
    }
}
