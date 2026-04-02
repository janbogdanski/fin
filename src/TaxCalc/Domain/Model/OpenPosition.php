<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

final class OpenPosition
{
    public function __construct(
        public readonly TransactionId $transactionId,
        public readonly \DateTimeImmutable $date,
        public readonly BigDecimal $originalQuantity,
        private BigDecimal $remainingQuantity,
        public readonly BigDecimal $costPerUnitPLN,
        public readonly BigDecimal $commissionPerUnitPLN,
        public readonly NBPRate $nbpRate,
        public readonly BrokerId $broker,
    ) {
    }

    public function remainingQuantity(): BigDecimal
    {
        return $this->remainingQuantity;
    }

    public function reduceQuantity(BigDecimal $quantity): void
    {
        $this->remainingQuantity = $this->remainingQuantity->minus($quantity);
    }

    public function isFullyConsumed(): bool
    {
        return $this->remainingQuantity->isZero() || $this->remainingQuantity->isNegative();
    }
}
