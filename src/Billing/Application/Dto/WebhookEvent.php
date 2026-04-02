<?php

declare(strict_types=1);

namespace App\Billing\Application\Dto;

final readonly class WebhookEvent
{
    public function __construct(
        public WebhookEventType $type,
        public string $sessionId,
        public string $transactionId,
    ) {
    }
}
