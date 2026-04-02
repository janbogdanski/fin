<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use App\Billing\Domain\ValueObject\PaymentStatus;
use App\Billing\Domain\ValueObject\ProductCode;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Component\Uid\Uuid;

final class Payment
{
    private ?string $stripePaymentIntentId = null;

    private function __construct(
        private readonly string $id,
        private readonly UserId $userId,
        private readonly string $stripeSessionId,
        private readonly ProductCode $productCode,
        private readonly int $amountCents,
        private readonly string $currency,
        private PaymentStatus $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        UserId $userId,
        string $stripeSessionId,
        ProductCode $productCode,
    ): self {
        return new self(
            id: Uuid::v7()->toRfc4122(),
            userId: $userId,
            stripeSessionId: $stripeSessionId,
            productCode: $productCode,
            amountCents: $productCode->amountCents(),
            currency: $productCode->currency(),
            status: PaymentStatus::PENDING,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function markAsPaid(string $stripePaymentIntentId): void
    {
        $this->status = PaymentStatus::PAID;
        $this->stripePaymentIntentId = $stripePaymentIntentId;
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

    public function stripeSessionId(): string
    {
        return $this->stripeSessionId;
    }

    public function stripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
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
