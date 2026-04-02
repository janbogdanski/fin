<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

/**
 * Wynik FIFO matching — zamknięta pozycja.
 * Immutable. Append-only (nie ładowana do agregatu, persisted osobno).
 */
final readonly class ClosedPosition
{
    /**
     * Maximum allowed difference between computed and provided gainLoss
     * (accounts for rounding in PLN conversion).
     */
    private const string GAIN_LOSS_TOLERANCE = '0.01';

    public function __construct(
        public TransactionId $buyTransactionId,
        public TransactionId $sellTransactionId,
        public ISIN $isin,
        public BigDecimal $quantity,
        public BigDecimal $costBasisPLN,
        public BigDecimal $proceedsPLN,
        public BigDecimal $buyCommissionPLN,
        public BigDecimal $sellCommissionPLN,
        public BigDecimal $gainLossPLN,
        public \DateTimeImmutable $buyDate,
        public \DateTimeImmutable $sellDate,
        public NBPRate $buyNBPRate,
        public NBPRate $sellNBPRate,
        public BrokerId $buyBroker,
        public BrokerId $sellBroker,
    ) {
        $this->assertGainLossInvariant();
    }

    /**
     * Verifies that gainLoss equals proceeds - costBasis - buyCommission - sellCommission
     * within an acceptable rounding tolerance.
     */
    private function assertGainLossInvariant(): void
    {
        $expected = $this->proceedsPLN
            ->minus($this->costBasisPLN)
            ->minus($this->buyCommissionPLN)
            ->minus($this->sellCommissionPLN);

        $difference = $this->gainLossPLN->minus($expected)->abs();

        if ($difference->isGreaterThan(BigDecimal::of(self::GAIN_LOSS_TOLERANCE))) {
            throw new \DomainException(sprintf(
                'ClosedPosition gainLoss invariant violated: gainLoss=%s but expected=%s (diff=%s, tolerance=%s)',
                $this->gainLossPLN,
                $expected,
                $difference,
                self::GAIN_LOSS_TOLERANCE,
            ));
        }
    }
}
