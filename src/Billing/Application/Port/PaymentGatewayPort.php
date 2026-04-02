<?php

declare(strict_types=1);

namespace App\Billing\Application\Port;

use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

interface PaymentGatewayPort
{
    /**
     * Creates a checkout session in the payment provider.
     *
     * @return array{sessionId: string, url: string}
     */
    public function createCheckoutSession(
        UserId $userId,
        ProductCode $productCode,
        string $successUrl,
        string $cancelUrl,
    ): array;

    /**
     * Retrieves the payment intent ID for a completed checkout session.
     */
    public function getPaymentIntentId(string $sessionId): string;

    /**
     * Verifies a webhook payload from the payment provider.
     * Returns parsed event data or null if signature is invalid.
     *
     * @return array{type: string, sessionId: string, paymentIntentId: string}|null
     */
    public function verifyWebhook(string $payload, string $signature): ?array;
}
