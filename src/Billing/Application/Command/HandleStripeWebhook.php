<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class HandleStripeWebhook
{
    public function __construct(
        public string $stripeSessionId,
        public string $stripePaymentIntentId,
    ) {
    }
}
