<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Shared\Domain\ValueObject\UserId;

interface ImportedTransactionRepositoryInterface
{
    /**
     * @param list<ImportedTransaction> $transactions
     */
    public function saveAll(array $transactions): void;

    /**
     * @return list<ImportedTransaction>
     */
    public function findByUser(UserId $userId): array;

    /**
     * @return list<ImportedTransaction>
     */
    public function findByUserAndYear(UserId $userId, int $year): array;

    /**
     * @return list<ImportedTransaction>
     */
    public function findByUserAndBatchId(UserId $userId, string $importBatchId): array;

    public function countByUser(UserId $userId): int;

    public function countBrokersByUser(UserId $userId): int;

    /**
     * Count SELL transactions for a user in a given year (proxy for closed positions).
     */
    public function countSellsByUserAndYear(UserId $userId, int $year): int;

    /**
     * Check if a content hash already exists for this user (dedup).
     */
    public function existsByContentHash(UserId $userId, string $contentHash): bool;

    public function deleteByBatch(UserId $userId, string $batchId): void;
}
