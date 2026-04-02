<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Stripe;

use App\Billing\Application\Dto\CheckoutSessionResult;
use App\Billing\Application\Dto\WebhookEvent;
use App\Billing\Application\Dto\WebhookEventType;
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
    ): CheckoutSessionResult {
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

        return new CheckoutSessionResult(
            sessionId: $session->id,
            checkoutUrl: (string) $session->url,
        );
    }

    public function verifyWebhook(string $payload, array $headers): ?WebhookEvent
    {
        $signature = $this->extractHeader($headers, 'stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return null;
        }

        if ($event->type !== 'checkout.session.completed') {
            return new WebhookEvent(
                type: WebhookEventType::OTHER,
                sessionId: '',
                transactionId: '',
            );
        }

        $session = $event->data->object;

        return new WebhookEvent(
            type: WebhookEventType::PAYMENT_COMPLETED,
            sessionId: (string) $session->id,
            transactionId: (string) ($session->payment_intent ?? ''),
        );
    }

    /**
     * Extracts a single header value from the headers array.
     * Symfony headers are lowercase-keyed with list values.
     *
     * @param array<string, list<string|null>> $headers
     */
    private function extractHeader(array $headers, string $name): string
    {
        $key = strtolower($name);

        foreach ($headers as $headerName => $values) {
            if (strtolower($headerName) === $key) {
                return (string) ($values[0] ?? '');
            }
        }

        return '';
    }
}
