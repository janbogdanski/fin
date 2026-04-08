<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;

final class InMemoryImportedTransactionRepository implements ImportedTransactionRepositoryInterface
{
    /**
     * @var array<string, array{total: int, brokers: int, sellsByYear: array<int, int>}>
     */
    private array $stats = [];

    /**
     * @param array<int, int> $sellsByYear
     */
    public function seedStats(UserId $userId, int $total, int $brokers, array $sellsByYear = []): void
    {
        $this->stats[$userId->toString()] = [
            'total' => $total,
            'brokers' => $brokers,
            'sellsByYear' => $sellsByYear,
        ];
    }

    public function saveAll(array $transactions): void
    {
    }

    public function findByUser(UserId $userId): array
    {
        return [];
    }

    public function findByUserAndYear(UserId $userId, int $year): array
    {
        return [];
    }

    public function findByUserAndBatchId(UserId $userId, string $importBatchId): array
    {
        return [];
    }

    public function countByUser(UserId $userId): int
    {
        return $this->stats[$userId->toString()]['total'] ?? 0;
    }

    public function countBrokersByUser(UserId $userId): int
    {
        return $this->stats[$userId->toString()]['brokers'] ?? 0;
    }

    public function countSellsByUserAndYear(UserId $userId, int $year): int
    {
        return $this->stats[$userId->toString()]['sellsByYear'][$year] ?? 0;
    }

    public function existsByContentHash(UserId $userId, string $contentHash): bool
    {
        return false;
    }

    public function deleteByBatch(UserId $userId, string $batchId): void
    {
    }
}
