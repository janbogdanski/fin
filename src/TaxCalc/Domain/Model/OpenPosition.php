<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class OpenPosition
{
    private BigDecimal $roundedRemainingCostBasisPLN;

    private BigDecimal $roundedRemainingCommissionPLN;

    public function __construct(
        public readonly TransactionId $transactionId,
        public readonly \DateTimeImmutable $date,
        public readonly BigDecimal $originalQuantity,
        private BigDecimal $remainingQuantity,
        public readonly BigDecimal $costPerUnitPLN,
        public readonly BigDecimal $commissionPerUnitPLN,
        public readonly NBPRate $nbpRate,
        public readonly BrokerId $broker,
        ?BigDecimal $roundedRemainingCostBasisPLN = null,
        ?BigDecimal $roundedRemainingCommissionPLN = null,
    ) {
        $this->roundedRemainingCostBasisPLN = $roundedRemainingCostBasisPLN
            ?? $this->roundAmount($this->costPerUnitPLN->multipliedBy($this->remainingQuantity));
        $this->roundedRemainingCommissionPLN = $roundedRemainingCommissionPLN
            ?? $this->roundAmount($this->commissionPerUnitPLN->multipliedBy($this->remainingQuantity));
    }

    public function remainingQuantity(): BigDecimal
    {
        return $this->remainingQuantity;
    }

    /**
     * @return array{0: BigDecimal, 1: BigDecimal} cost basis PLN and buy commission PLN
     */
    public function consume(BigDecimal $quantity): array
    {
        if ($quantity->isGreaterThan($this->remainingQuantity)) {
            throw new \LogicException(
                "Cannot reduce by {$quantity} — only {$this->remainingQuantity} remaining",
            );
        }

        $costBasisPLN = $this->allocateRoundedAmount(
            $this->costPerUnitPLN,
            $quantity,
            $this->roundedRemainingCostBasisPLN,
        );
        $commissionPLN = $this->allocateRoundedAmount(
            $this->commissionPerUnitPLN,
            $quantity,
            $this->roundedRemainingCommissionPLN,
        );

        $this->remainingQuantity = $this->remainingQuantity->minus($quantity);
        $this->roundedRemainingCostBasisPLN = $this->roundedRemainingCostBasisPLN->minus($costBasisPLN);
        $this->roundedRemainingCommissionPLN = $this->roundedRemainingCommissionPLN->minus($commissionPLN);

        return [$costBasisPLN, $commissionPLN];
    }

    public function reduceQuantity(BigDecimal $quantity): void
    {
        $this->consume($quantity);
    }

    public function isFullyConsumed(): bool
    {
        return $this->remainingQuantity->isZero();
    }

    public function roundedRemainingCostBasisPLN(): BigDecimal
    {
        return $this->roundedRemainingCostBasisPLN;
    }

    public function roundedRemainingCommissionPLN(): BigDecimal
    {
        return $this->roundedRemainingCommissionPLN;
    }

    private function allocateRoundedAmount(
        BigDecimal $perUnitPLN,
        BigDecimal $quantity,
        BigDecimal $roundedRemainingPLN,
    ): BigDecimal {
        if ($quantity->isEqualTo($this->remainingQuantity)) {
            return $roundedRemainingPLN;
        }

        return $this->roundAmount($perUnitPLN->multipliedBy($quantity));
    }

    private function roundAmount(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(2, RoundingMode::HALF_UP);
    }
}
