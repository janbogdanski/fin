<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Exception\InsufficientSharesException;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Aggregate Root.
 * Per ISIN × User (BEZ TaxYear — FIFO jest ciągłe cross-year).
 * Cross-broker (FIFO per instrument, nie per broker).
 *
 * closedPositions NIE są ładowane do agregatu (append-only).
 * Aggregate operuje TYLKO na openPositions (FIFO queue).
 *
 * @see ADR-017 (Multi-Year FIFO)
 * @see art. 24 ust. 10 ustawy o PIT (zasada FIFO)
 */
final class TaxPositionLedger
{
    private UserId $userId;

    private ISIN $isin;

    private TaxCategory $taxCategory;

    /**
     * @var list<OpenPosition> sorted by date ASC — FIFO queue
     */
    private array $openPositions = [];

    /**
     * @var list<ClosedPosition> nowo utworzone — do zapisu, NIE ładowane z DB
     */
    private array $newClosedPositions = [];

    public static function create(
        UserId $userId,
        ISIN $isin,
        TaxCategory $taxCategory,
    ): self {
        $ledger = new self();
        $ledger->userId = $userId;
        $ledger->isin = $isin;
        $ledger->taxCategory = $taxCategory;

        return $ledger;
    }

    public function registerBuy(
        TransactionId $txId,
        \DateTimeImmutable $date,
        BigDecimal $quantity,
        Money $pricePerUnit,
        Money $commission,
        BrokerId $broker,
        NBPRate $nbpRate,
    ): void {
        $this->guardPositiveQuantity($quantity);
        $this->guardNonNegativePrice($pricePerUnit);

        $totalCostPLN = CurrencyConverter::toPLN($pricePerUnit->multiply($quantity), $nbpRate);
        $commissionPLN = CurrencyConverter::toPLN($commission, $nbpRate);

        $costPerUnitPLN = $totalCostPLN->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        $commissionPerUnitPLN = $commissionPLN->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        $this->openPositions[] = new OpenPosition(
            transactionId: $txId,
            date: $date,
            originalQuantity: $quantity,
            remainingQuantity: $quantity,
            costPerUnitPLN: $costPerUnitPLN,
            commissionPerUnitPLN: $commissionPerUnitPLN,
            nbpRate: $nbpRate,
            broker: $broker,
        );

        usort(
            $this->openPositions,
            fn (OpenPosition $a, OpenPosition $b) =>
            $a->date <=> $b->date
                ?: $a->transactionId->toString() <=> $b->transactionId->toString()
        );
    }

    /**
     * @return list<ClosedPosition> wynik FIFO matching
     */
    public function registerSell(
        TransactionId $txId,
        \DateTimeImmutable $date,
        BigDecimal $quantity,
        Money $pricePerUnit,
        Money $commission,
        BrokerId $broker,
        NBPRate $nbpRate,
    ): array {
        $this->guardPositiveQuantity($quantity);
        $this->guardNonNegativePrice($pricePerUnit);

        // Pre-check: total available shares >= sell quantity (atomic guard)
        $this->guardSufficientShares($quantity);

        $remainingToSell = $quantity;
        $matched = [];

        $proceedsPerUnitPLN = CurrencyConverter::toPLN($pricePerUnit, $nbpRate)->amount();
        $sellCommPerUnitPLN = CurrencyConverter::toPLN($commission, $nbpRate)->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        while ($remainingToSell->isPositive()) {
            $oldest = $this->findOldestOpenPosition();

            // Should never happen after pre-check, but defensive guard
            if ($oldest === null) {
                throw new InsufficientSharesException($this->isin, $remainingToSell);
            }

            $matchQuantity = BigDecimal::min($remainingToSell, $oldest->remainingQuantity());

            // Per-unit × quantity — precyzja intermediate (scale 8+)
            $costBasisPLN = $oldest->costPerUnitPLN->multipliedBy($matchQuantity);
            $buyCommPLN = $oldest->commissionPerUnitPLN->multipliedBy($matchQuantity);
            $proceedsPLN = $proceedsPerUnitPLN->multipliedBy($matchQuantity);
            $sellCommPLN = $sellCommPerUnitPLN->multipliedBy($matchQuantity);

            $gainLoss = $proceedsPLN
                ->minus($costBasisPLN)
                ->minus($buyCommPLN)
                ->minus($sellCommPLN);

            $closed = new ClosedPosition(
                buyTransactionId: $oldest->transactionId,
                sellTransactionId: $txId,
                isin: $this->isin,
                quantity: $matchQuantity,
                costBasisPLN: $costBasisPLN->toScale(2, RoundingMode::HALF_UP),
                proceedsPLN: $proceedsPLN->toScale(2, RoundingMode::HALF_UP),
                buyCommissionPLN: $buyCommPLN->toScale(2, RoundingMode::HALF_UP),
                sellCommissionPLN: $sellCommPLN->toScale(2, RoundingMode::HALF_UP),
                gainLossPLN: $gainLoss->toScale(2, RoundingMode::HALF_UP),
                buyDate: $oldest->date,
                sellDate: $date,
                buyNBPRate: $oldest->nbpRate,
                sellNBPRate: $nbpRate,
                buyBroker: $oldest->broker,
                sellBroker: $broker,
            );

            $matched[] = $closed;
            $this->newClosedPositions[] = $closed;

            $oldest->reduceQuantity($matchQuantity);
            if ($oldest->isFullyConsumed()) {
                $this->removeOpenPosition($oldest);
            }

            $remainingToSell = $remainingToSell->minus($matchQuantity);
        }

        return $matched;
    }

    /**
     * @return list<ClosedPosition>
     */
    public function flushNewClosedPositions(): array
    {
        $new = $this->newClosedPositions;
        $this->newClosedPositions = [];

        return $new;
    }

    /**
     * @return list<OpenPosition>
     */
    public function openPositions(): array
    {
        return $this->openPositions;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function isin(): ISIN
    {
        return $this->isin;
    }

    public function taxCategory(): TaxCategory
    {
        return $this->taxCategory;
    }

    private function findOldestOpenPosition(): ?OpenPosition
    {
        foreach ($this->openPositions as $position) {
            if (! $position->isFullyConsumed()) {
                return $position;
            }
        }

        return null;
    }

    private function removeOpenPosition(OpenPosition $target): void
    {
        $this->openPositions = array_values(
            array_filter(
                $this->openPositions,
                fn (OpenPosition $p) => $p !== $target,
            ),
        );
    }

    private function guardPositiveQuantity(BigDecimal $quantity): void
    {
        if (! $quantity->isPositive()) {
            throw new \InvalidArgumentException(
                "Quantity must be greater than 0, got: {$quantity}",
            );
        }
    }

    private function guardNonNegativePrice(Money $pricePerUnit): void
    {
        if ($pricePerUnit->amount()->isNegative()) {
            throw new \InvalidArgumentException(
                "Price per unit cannot be negative, got: {$pricePerUnit->amount()}",
            );
        }
    }

    /**
     * Pre-check before FIFO matching: ensures total available shares >= sell quantity.
     * Prevents partial state mutation on InsufficientSharesException.
     *
     * @see P0-009 (Sprint 3 QA review: registerSell atomicity)
     */
    private function guardSufficientShares(BigDecimal $quantity): void
    {
        $totalAvailable = BigDecimal::zero();

        foreach ($this->openPositions as $position) {
            if (! $position->isFullyConsumed()) {
                $totalAvailable = $totalAvailable->plus($position->remainingQuantity());
            }
        }

        if ($totalAvailable->isLessThan($quantity)) {
            throw new InsufficientSharesException(
                $this->isin,
                $quantity->minus($totalAvailable),
            );
        }
    }
}
