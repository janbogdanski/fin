<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Doctrine;

use App\Billing\Application\Port\PaymentRepositoryPort;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePaymentRepository implements PaymentRepositoryPort
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function findByStripeSessionId(string $sessionId): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)
            ->findOneBy([
                'stripeSessionId' => $sessionId,
            ]);
    }

    public function hasSuccessfulPayment(UserId $userId, ProductCode $productCode): bool
    {
        $result = $this->entityManager->getRepository(Payment::class)
            ->findOneBy([
                'userId' => $userId,
                'productCode' => $productCode,
                'status' => PaymentStatus::PAID,
            ]);

        return $result !== null;
    }

    public function hasActivePaymentForTier(UserId $userId, ProductCode $minimumTier): bool
    {
        // PRO covers STANDARD as well
        if ($minimumTier === ProductCode::STANDARD) {
            return $this->hasSuccessfulPayment($userId, ProductCode::STANDARD)
                || $this->hasSuccessfulPayment($userId, ProductCode::PRO);
        }

        return $this->hasSuccessfulPayment($userId, ProductCode::PRO);
    }
}
