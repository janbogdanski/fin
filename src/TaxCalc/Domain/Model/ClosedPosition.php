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
    }
}
