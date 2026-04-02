<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Stripe;

use App\Billing\Application\Port\PaymentGatewayPort;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

final readonly class StripePaymentGateway implements PaymentGatewayPort
{
    private StripeClient $stripe;

    public function __construct(
        string $stripeSecretKey,
        private string $stripeWebhookSecret,
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    public function createCheckoutSession(
        UserId $userId,
        ProductCode $productCode,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card', 'p24'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($productCode->currency()),
                    'product_data' => [
                        'name' => $productCode->label(),
                    ],
                    'unit_amount' => $productCode->amountCents(),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $userId->toString(),
                'product_code' => $productCode->value,
            ],
        ]);

        return [
            'sessionId' => $session->id,
            'url' => (string) $session->url,
        ];
    }

    public function getPaymentIntentId(string $sessionId): string
    {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId);

        return (string) $session->payment_intent;
    }

    public function verifyWebhook(string $payload, string $signature): ?array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return null;
        }

        if ($event->type !== 'checkout.session.completed') {
            return [
                'type' => $event->type,
                'sessionId' => '',
                'paymentIntentId' => '',
            ];
        }

        $session = $event->data->object;

        return [
            'type' => $event->type,
            'sessionId' => (string) $session->id,
            'paymentIntentId' => (string) ($session->payment_intent ?? ''),
        ];
    }
}
