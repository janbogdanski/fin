<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class CreateCheckoutSessionResult
{
    public function __construct(
        public string $checkoutUrl,
    ) {
    }
}
