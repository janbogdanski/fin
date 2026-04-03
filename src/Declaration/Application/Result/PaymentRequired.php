<?php

declare(strict_types=1);

namespace App\Declaration\Application\Result;

use App\Billing\Domain\ValueObject\ProductCode;

/**
 * User's usage exceeds free tier — payment required before export.
 */
final readonly class PaymentRequired implements DeclarationResult
{
    public function __construct(
        public ProductCode $requiredProduct,
    ) {
    }
}
