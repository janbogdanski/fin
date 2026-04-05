<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Port for storing and retrieving imported transactions.
 *
 * Replaces the session-based ImportSessionStorage with persistent storage.
 * Controllers depend on this interface; infrastructure provides Doctrine implementation.
 */
interface ImportStoragePort
{
    /**
     * Persist a batch of parsed transactions for the given user.
     *
     * @param list<NormalizedTransaction> $transactions
     * @return string The import batch ID (UUID) for this upload
     */
    public function store(UserId $userId, BrokerId $brokerId, array $transactions, string $contentHash): string;

    /**
     * Get all transactions for a user as NormalizedTransactions (for FIFO processing).
     *
     * @return list<NormalizedTransaction>
     */
    public function getAllTransactions(UserId $userId): array;

    /**
     * Number of distinct brokers the user has imported from.
     */
    public function getBrokerCount(UserId $userId): int;

    /**
     * Total number of imported transactions for the user.
     */
    public function getTotalTransactionCount(UserId $userId): int;

    /**
     * Number of SELL transactions in a given year (proxy for closed positions).
     */
    public function getClosedPositionCount(UserId $userId, int $year): int;

    /**
     * Check if a file with this content hash was already imported by this user.
     */
    public function wasAlreadyImported(UserId $userId, string $contentHash): bool;
}
