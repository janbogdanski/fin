<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Shared\Domain\ValueObject\UserId;

final class InMemoryImportedTransactionRepository implements ImportedTransactionRepositoryInterface
{
    /**
     * @var array<string, list<ImportedTransaction>>
     */
    private array $transactions = [];

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
        foreach ($transactions as $transaction) {
            $this->transactions[$transaction->userId->toString()][] = $transaction;
        }
    }

    public function findByUser(UserId $userId): array
    {
        $transactions = $this->transactions[$userId->toString()] ?? [];
        usort($transactions, static fn (ImportedTransaction $a, ImportedTransaction $b): int => $a->date <=> $b->date);

        return $transactions;
    }

    public function findByUserAndYear(UserId $userId, int $year): array
    {
        return array_values(array_filter(
            $this->findByUser($userId),
            static fn (ImportedTransaction $transaction): bool => (int) $transaction->date->format('Y') === $year,
        ));
    }

    public function findByUserAndBatchId(UserId $userId, string $importBatchId): array
    {
        return array_values(array_filter(
            $this->findByUser($userId),
            static fn (ImportedTransaction $transaction): bool => $transaction->importBatchId === $importBatchId,
        ));
    }

    public function findByUserAndIds(UserId $userId, array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $transactionIdLookup = array_fill_keys($transactionIds, true);

        return array_values(array_filter(
            $this->findByUser($userId),
            static fn (ImportedTransaction $transaction): bool => isset($transactionIdLookup[$transaction->id->toString()]),
        ));
    }

    public function countByUser(UserId $userId): int
    {
        return $this->stats[$userId->toString()]['total'] ?? count($this->transactions[$userId->toString()] ?? []);
    }

    public function countBrokersByUser(UserId $userId): int
    {
        if (isset($this->stats[$userId->toString()]['brokers'])) {
            return $this->stats[$userId->toString()]['brokers'];
        }

        $brokers = [];
        foreach ($this->transactions[$userId->toString()] ?? [] as $transaction) {
            $brokers[$transaction->broker->toString()] = true;
        }

        return count($brokers);
    }

    public function countSellsByUserAndYear(UserId $userId, int $year): int
    {
        if (isset($this->stats[$userId->toString()]['sellsByYear'][$year])) {
            return $this->stats[$userId->toString()]['sellsByYear'][$year];
        }

        return count(array_filter(
            $this->transactions[$userId->toString()] ?? [],
            static fn (ImportedTransaction $transaction): bool => $transaction->transactionType === TransactionType::SELL->value
                && (int) $transaction->date->format('Y') === $year,
        ));
    }

    public function existsByContentHash(UserId $userId, string $contentHash): bool
    {
        foreach ($this->transactions[$userId->toString()] ?? [] as $transaction) {
            if ($transaction->contentHash === $contentHash) {
                return true;
            }
        }

        return false;
    }

    public function deleteByBatch(UserId $userId, string $batchId): void
    {
        $this->transactions[$userId->toString()] = array_values(array_filter(
            $this->transactions[$userId->toString()] ?? [],
            static fn (ImportedTransaction $transaction): bool => $transaction->importBatchId !== $batchId,
        ));
    }
}
