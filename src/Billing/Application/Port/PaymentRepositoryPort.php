<?php

declare(strict_types=1);

namespace App\Billing\Application\Port;

use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

interface PaymentRepositoryPort
{
    public function save(Payment $payment): void;

    public function flush(): void;

    public function findByStripeSessionId(string $sessionId): ?Payment;

    public function hasSuccessfulPayment(UserId $userId, ProductCode $productCode): bool;

    /**
     * Checks if user has any successful payment covering at least the given product tier.
     */
    public function hasActivePaymentForTier(UserId $userId, ProductCode $minimumTier): bool;
}
