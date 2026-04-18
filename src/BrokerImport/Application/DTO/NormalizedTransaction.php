<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

final readonly class NormalizedTransaction
{
    /**
     * Returns the grouping key for FIFO processing.
     *
     * When an ISIN is present it is the canonical key (ISO 6166).
     * When a broker does not supply an ISIN (e.g. XTB), the raw symbol is used as a fallback
     * so that the FIFO pipeline can still match buys against sells for the same instrument.
     */
    public function instrumentKey(): string
    {
        return $this->isin?->toString() ?? $this->symbol;
    }

    /**
     * @param array<string, string> $rawData
     */
    public function __construct(
        public TransactionId $id,
        public ?ISIN $isin,
        public string $symbol,
        public TransactionType $type,
        public \DateTimeImmutable $date,
        public BigDecimal $quantity,
        public Money $pricePerUnit,
        public Money $commission,
        public BrokerId $broker,
        public string $description,
        public array $rawData,
    ) {
    }
}
