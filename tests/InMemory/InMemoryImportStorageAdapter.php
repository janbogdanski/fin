<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of ImportStoragePort for testing.
 *
 * Stores NormalizedTransaction DTOs directly — no entity conversion needed.
 * Each stored entry includes metadata (userId, brokerId, batchId, contentHash)
 * to support all query methods defined by the port.
 */
final class InMemoryImportStorageAdapter implements ImportStoragePort
{
    /**
     * @var list<array{
     *     userId: string,
     *     brokerId: string,
     *     batchId: string,
     *     contentHash: string,
     *     transaction: NormalizedTransaction,
     * }>
     */
    private array $entries = [];

    public function store(UserId $userId, BrokerId $brokerId, array $transactions, string $contentHash): string
    {
        if ($transactions === []) {
            return '';
        }

        $batchId = Uuid::v7()->toRfc4122();

        foreach ($transactions as $tx) {
            $this->entries[] = [
                'userId' => $userId->toString(),
                'brokerId' => $brokerId->toString(),
                'batchId' => $batchId,
                'contentHash' => $contentHash,
                'transaction' => $tx,
            ];
        }

        return $batchId;
    }

    public function getAllTransactions(UserId $userId): array
    {
        $result = [];

        foreach ($this->entries as $entry) {
            if ($entry['userId'] === $userId->toString()) {
                $result[] = $entry['transaction'];
            }
        }

        return $result;
    }

    public function getBrokerCount(UserId $userId): int
    {
        $brokers = [];

        foreach ($this->entries as $entry) {
            if ($entry['userId'] === $userId->toString()) {
                $brokers[$entry['brokerId']] = true;
            }
        }

        return count($brokers);
    }

    public function getTotalTransactionCount(UserId $userId): int
    {
        $count = 0;

        foreach ($this->entries as $entry) {
            if ($entry['userId'] === $userId->toString()) {
                ++$count;
            }
        }

        return $count;
    }

    public function getClosedPositionCount(UserId $userId, int $year): int
    {
        $count = 0;

        foreach ($this->entries as $entry) {
            if (
                $entry['userId'] === $userId->toString()
                && $entry['transaction']->type === TransactionType::SELL
                && (int) $entry['transaction']->date->format('Y') === $year
            ) {
                ++$count;
            }
        }

        return $count;
    }

    public function wasAlreadyImported(UserId $userId, string $contentHash): bool
    {
        foreach ($this->entries as $entry) {
            if (
                $entry['userId'] === $userId->toString()
                && $entry['contentHash'] === $contentHash
            ) {
                return true;
            }
        }

        return false;
    }
}
