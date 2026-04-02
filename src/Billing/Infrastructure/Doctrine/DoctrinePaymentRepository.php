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

    public function findByProviderSessionId(string $sessionId): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)
            ->findOneBy([
                'providerSessionId' => $sessionId,
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
        foreach (ProductCode::cases() as $productCode) {
            if ($productCode->coversAtLeast($minimumTier) && $this->hasSuccessfulPayment($userId, $productCode)) {
                return true;
            }
        }

        return false;
    }
}
