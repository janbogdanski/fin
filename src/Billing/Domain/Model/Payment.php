<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Component\Uid\Uuid;

final class Payment
{
    private ?string $providerTransactionId = null;

    private function __construct(
        private readonly string $id,
        private readonly UserId $userId,
        private readonly string $providerSessionId,
        private readonly ProductCode $productCode,
        private readonly int $amountCents,
        private readonly string $currency,
        private PaymentStatus $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        UserId $userId,
        string $providerSessionId,
        ProductCode $productCode,
    ): self {
        return new self(
            id: Uuid::v7()->toRfc4122(),
            userId: $userId,
            providerSessionId: $providerSessionId,
            productCode: $productCode,
            amountCents: $productCode->amountCents(),
            currency: $productCode->currency(),
            status: PaymentStatus::PENDING,
            createdAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Marks this payment as paid. Idempotent: silently ignores if already paid.
     *
     * @throws \DomainException if the payment has failed and cannot be marked as paid
     */
    public function markAsPaid(string $providerTransactionId): void
    {
        if ($this->status === PaymentStatus::PAID) {
            return;
        }

        if ($this->status !== PaymentStatus::PENDING) {
            throw new \DomainException(
                sprintf('Cannot mark payment %s as paid — current status is %s.', $this->id, $this->status->value),
            );
        }

        $this->status = PaymentStatus::PAID;
        $this->providerTransactionId = $providerTransactionId;
    }

    public function markAsFailed(): void
    {
        $this->status = PaymentStatus::FAILED;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function providerSessionId(): string
    {
        return $this->providerSessionId;
    }

    public function providerTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function productCode(): ProductCode
    {
        return $this->productCode;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }
}
