<?php

declare(strict_types=1);

namespace App\Declaration\Application\Port;

use App\Declaration\Domain\DTO\SourceTransactionSnapshot;
use App\Shared\Domain\ValueObject\UserId;

interface SourceTransactionLookupPort
{
    /**
     * @param list<string> $transactionIds
     *
     * @return list<SourceTransactionSnapshot>
     */
    public function findByUserAndIds(UserId $userId, array $transactionIds): array;
}
