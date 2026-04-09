<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

final readonly class SourceTransactionSnapshot
{
    public function __construct(
        public string $transactionId,
        public string $symbol,
        public string $pricePerUnit,
        public string $priceCurrency,
    ) {
    }
}
