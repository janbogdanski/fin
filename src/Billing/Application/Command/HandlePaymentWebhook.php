<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class HandlePaymentWebhook
{
    public function __construct(
        public string $providerSessionId,
        public string $providerTransactionId,
    ) {
    }
}
