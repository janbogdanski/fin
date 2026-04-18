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
