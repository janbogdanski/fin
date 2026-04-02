<?php

declare(strict_types=1);

namespace App\Billing\Application\Port;

use App\Billing\Application\Dto\CheckoutSessionResult;
use App\Billing\Application\Dto\WebhookEvent;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

interface PaymentGatewayPort
{
    /**
     * Creates a checkout session in the payment provider.
     */
    public function createCheckoutSession(
        UserId $userId,
        ProductCode $productCode,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult;

    /**
     * Verifies a webhook payload from the payment provider.
     * The adapter extracts the correct signature header internally.
     *
     * @param array<string, list<string|null>> $headers all request headers
     *
     * @return WebhookEvent|null null when signature is invalid
     */
    public function verifyWebhook(string $payload, array $headers): ?WebhookEvent;
}
