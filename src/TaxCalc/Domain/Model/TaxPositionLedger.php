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
use App\TaxCalc\Domain\Service\CurrencyConverterInterface;
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

    /**
     * Reconstitute aggregate from persistence (bypasses domain rules).
     * Used by repositories to hydrate the aggregate with pre-existing open positions.
     *
     * @param list<OpenPosition> $openPositions sorted by date ASC
     */
    public static function reconstitute(
        UserId $userId,
        ISIN $isin,
        TaxCategory $taxCategory,
        array $openPositions,
    ): self {
        $ledger = new self();
        $ledger->userId = $userId;
        $ledger->isin = $isin;
        $ledger->taxCategory = $taxCategory;
        $ledger->openPositions = $openPositions;

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
        CurrencyConverterInterface $converter,
    ): void {
        $this->guardPositiveQuantity($quantity);
        $this->guardNonNegativePrice($pricePerUnit);
        $this->guardNonNegativeCommission($commission);

        $totalCostPLN = $converter->toPLN($pricePerUnit->multiply($quantity), $nbpRate);
        $commissionPLN = $converter->toPLN($commission, $nbpRate);

        $costPerUnitPLN = $totalCostPLN->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        $commissionPerUnitPLN = $commissionPLN->amount()
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);

        $position = new OpenPosition(
            transactionId: $txId,
            date: $date,
            originalQuantity: $quantity,
            remainingQuantity: $quantity,
            costPerUnitPLN: $costPerUnitPLN,
            commissionPerUnitPLN: $commissionPerUnitPLN,
            nbpRate: $nbpRate,
            broker: $broker,
            roundedRemainingCostBasisPLN: $totalCostPLN->amount()->toScale(2, RoundingMode::HALF_UP),
            roundedRemainingCommissionPLN: $commissionPLN->amount()->toScale(2, RoundingMode::HALF_UP),
        );

        $insertIndex = $this->findInsertionIndex($position);
        array_splice($this->openPositions, $insertIndex, 0, [$position]);
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
        CurrencyConverterInterface $converter,
    ): array {
        $this->guardPositiveQuantity($quantity);
        $this->guardNonNegativePrice($pricePerUnit);
        $this->guardNonNegativeCommission($commission);

        // Pre-check: total available shares >= sell quantity (atomic guard)
        $this->guardSufficientShares($quantity);

        $remainingToSell = $quantity;
        $matched = [];

        $totalProceedsPLN = $converter->toPLN($pricePerUnit->multiply($quantity), $nbpRate)->amount();
        $totalSellCommPLN = $converter->toPLN($commission, $nbpRate)->amount();
        $proceedsPerUnitPLN = $totalProceedsPLN
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);
        $sellCommPerUnitPLN = $totalSellCommPLN
            ->dividedBy($quantity, 8, RoundingMode::HALF_UP);
        $roundedProceedsRemaining = $totalProceedsPLN->toScale(2, RoundingMode::HALF_UP);
        $roundedSellCommRemaining = $totalSellCommPLN->toScale(2, RoundingMode::HALF_UP);

        while ($remainingToSell->isPositive()) {
            $oldest = $this->findOldestOpenPosition();

            // Should never happen after pre-check, but defensive guard
            if ($oldest === null) {
                throw new InsufficientSharesException($this->isin, $remainingToSell);
            }

            $matchQuantity = BigDecimal::min($remainingToSell, $oldest->remainingQuantity());
            $isLastSellMatch = $matchQuantity->isEqualTo($remainingToSell);

            [$costBasisPLN, $buyCommPLN] = $oldest->consume($matchQuantity);
            $proceedsPLN = $this->allocateRoundedSellAmount(
                $proceedsPerUnitPLN,
                $matchQuantity,
                $roundedProceedsRemaining,
                $isLastSellMatch,
            );
            $sellCommPLN = $this->allocateRoundedSellAmount(
                $sellCommPerUnitPLN,
                $matchQuantity,
                $roundedSellCommRemaining,
                $isLastSellMatch,
            );
            $roundedProceedsRemaining = $roundedProceedsRemaining->minus($proceedsPLN);
            $roundedSellCommRemaining = $roundedSellCommRemaining->minus($sellCommPLN);

            $gainLoss = $proceedsPLN
                ->minus($costBasisPLN)
                ->minus($buyCommPLN)
                ->minus($sellCommPLN);

            $closed = new ClosedPosition(
                buyTransactionId: $oldest->transactionId,
                sellTransactionId: $txId,
                isin: $this->isin,
                quantity: $matchQuantity,
                costBasisPLN: $costBasisPLN,
                proceedsPLN: $proceedsPLN,
                buyCommissionPLN: $buyCommPLN,
                sellCommissionPLN: $sellCommPLN,
                gainLossPLN: $gainLoss,
                buyDate: $oldest->date,
                sellDate: $date,
                buyNBPRate: $oldest->nbpRate,
                sellNBPRate: $nbpRate,
                buyBroker: $oldest->broker,
                sellBroker: $broker,
            );

            $matched[] = $closed;
            $this->newClosedPositions[] = $closed;

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

    private function allocateRoundedSellAmount(
        BigDecimal $perUnitPLN,
        BigDecimal $quantity,
        BigDecimal $roundedRemainingPLN,
        bool $isLastSellMatch,
    ): BigDecimal {
        if ($isLastSellMatch) {
            return $roundedRemainingPLN;
        }

        return $perUnitPLN->multipliedBy($quantity)->toScale(2, RoundingMode::HALF_UP);
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
        // FIFO: always consuming from the front (oldest first)
        if (isset($this->openPositions[0]) && $this->openPositions[0] === $target) {
            array_shift($this->openPositions);

            return;
        }

        // Fallback for non-front removal (shouldn't happen in normal FIFO)
        $this->openPositions = array_values(
            array_filter(
                $this->openPositions,
                fn (OpenPosition $p) => $p !== $target,
            ),
        );
    }

    /**
     * Binary search to find correct insertion index maintaining sorted order.
     * Comparator: date ASC, then transactionId ASC (same as previous usort).
     */
    private function findInsertionIndex(OpenPosition $position): int
    {
        $low = 0;
        $high = count($this->openPositions);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            $existing = $this->openPositions[$mid];

            $cmp = $existing->date <=> $position->date
                ?: $existing->transactionId->toString() <=> $position->transactionId->toString();

            if ($cmp <= 0) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low;
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

    private function guardNonNegativeCommission(Money $commission): void
    {
        if ($commission->amount()->isNegative()) {
            throw new \InvalidArgumentException(
                "Commission cannot be negative, got: {$commission->amount()}",
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
