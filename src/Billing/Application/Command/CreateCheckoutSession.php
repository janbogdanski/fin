<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

final readonly class CreateCheckoutSession
{
    public function __construct(
        public UserId $userId,
        public ProductCode $productCode,
        public string $successUrl,
        public string $cancelUrl,
    ) {
    }
}
