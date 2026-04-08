<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;

final class InMemoryPaymentRepository implements PaymentRepositoryPort
{
    /**
     * @var array<string, Payment>
     */
    private array $payments = [];

    public function save(Payment $payment): void
    {
        $this->payments[$payment->id()] = $payment;
    }

    public function flush(): void
    {
    }

    public function findByProviderSessionId(string $sessionId): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->providerSessionId() === $sessionId) {
                return $payment;
            }
        }

        return null;
    }

    public function hasSuccessfulPayment(UserId $userId, ProductCode $productCode): bool
    {
        foreach ($this->payments as $payment) {
            if (
                $payment->userId()->equals($userId)
                && $payment->productCode() === $productCode
                && $payment->isPaid()
            ) {
                return true;
            }
        }

        return false;
    }

    public function hasActivePaymentForTier(UserId $userId, ProductCode $minimumTier): bool
    {
        foreach ($this->payments as $payment) {
            if (
                $payment->userId()->equals($userId)
                && $payment->isPaid()
                && $payment->productCode()->coversAtLeast($minimumTier)
            ) {
                return true;
            }
        }

        return false;
    }
}
