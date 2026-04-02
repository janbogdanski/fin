<?php

declare(strict_types=1);

namespace App\Billing\Application\Dto;

final readonly class CheckoutSessionResult
{
    public function __construct(
        public string $sessionId,
        public string $checkoutUrl,
    ) {
    }
}
