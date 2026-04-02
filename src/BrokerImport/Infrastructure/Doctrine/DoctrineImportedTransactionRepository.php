<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Doctrine;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Shared\Domain\PolishTimezone;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

/**
 * DBAL-based repository for ImportedTransaction.
 * Uses raw DBAL (same pattern as DoctrineTaxPositionLedgerRepository)
 * to avoid polluting domain model with ORM annotations.
 */
final readonly class DoctrineImportedTransactionRepository implements ImportedTransactionRepositoryInterface
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function saveAll(array $transactions): void
    {
        if ($transactions === []) {
            return;
        }

        $columns = [
            'id', 'user_id', 'import_batch_id', 'broker_id', 'isin', 'symbol',
            'transaction_type', 'transaction_date', 'quantity', 'price_amount',
            'price_currency', 'commission_amount', 'commission_currency',
            'description', 'content_hash', 'created_at',
        ];

        foreach (array_chunk($transactions, self::BATCH_SIZE) as $batch) {
            $placeholders = [];
            $params = [];
            $paramIndex = 0;

            foreach ($batch as $tx) {
                $row = [
                    $tx->id->toString(),
                    $tx->userId->toString(),
                    $tx->importBatchId,
                    $tx->broker->toString(),
                    $tx->isin?->toString(),
                    $tx->symbol,
                    $tx->transactionType,
                    $tx->date->format('Y-m-d H:i:s'),
                    (string) $tx->quantity,
                    (string) $tx->pricePerUnit->amount(),
                    $tx->pricePerUnit->currency()->value,
                    (string) $tx->commission->amount(),
                    $tx->commission->currency()->value,
                    $tx->description,
                    $tx->contentHash,
                    $tx->createdAt->format('Y-m-d H:i:s'),
                ];

                $rowPlaceholders = [];
                foreach ($row as $value) {
                    $paramName = 'p' . $paramIndex++;
                    $rowPlaceholders[] = ':' . $paramName;
                    $params[$paramName] = $value;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }

            $sql = sprintf(
                'INSERT INTO imported_transactions (%s) VALUES %s',
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $this->connection->executeStatement($sql, $params);
        }
    }

    public function findByUser(UserId $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM imported_transactions WHERE user_id = :userId ORDER BY transaction_date ASC',
            [
                'userId' => $userId->toString(),
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    public function findByUserAndYear(UserId $userId, int $year): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT * FROM imported_transactions
                WHERE user_id = :userId
                  AND transaction_date >= :yearStart
                  AND transaction_date < :yearEnd
                ORDER BY transaction_date ASC
            SQL,
            [
                'userId' => $userId->toString(),
                'yearStart' => sprintf('%d-01-01 00:00:00', $year),
                'yearEnd' => sprintf('%d-01-01 00:00:00', $year + 1),
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    public function findByUserAndBatchId(UserId $userId, string $importBatchId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM imported_transactions WHERE user_id = :userId AND import_batch_id = :batchId ORDER BY transaction_date ASC',
            [
                'userId' => $userId->toString(),
                'batchId' => $importBatchId,
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    public function countByUser(UserId $userId): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM imported_transactions WHERE user_id = :userId',
            [
                'userId' => $userId->toString(),
            ],
        );

        return (int) $result;
    }

    public function countBrokersByUser(UserId $userId): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT broker_id) FROM imported_transactions WHERE user_id = :userId',
            [
                'userId' => $userId->toString(),
            ],
        );

        return (int) $result;
    }

    public function countSellsByUserAndYear(UserId $userId, int $year): int
    {
        $result = $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM imported_transactions
                WHERE user_id = :userId
                  AND transaction_type = :sellType
                  AND transaction_date >= :yearStart
                  AND transaction_date < :yearEnd
            SQL,
            [
                'userId' => $userId->toString(),
                'sellType' => TransactionType::SELL->value,
                'yearStart' => sprintf('%d-01-01 00:00:00', $year),
                'yearEnd' => sprintf('%d-01-01 00:00:00', $year + 1),
            ],
        );

        return (int) $result;
    }

    public function existsByContentHash(UserId $userId, string $contentHash): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1 FROM imported_transactions WHERE user_id = :userId AND content_hash = :hash LIMIT 1',
            [
                'userId' => $userId->toString(),
                'hash' => $contentHash,
            ],
        );

        return $result !== false;
    }

    public function deleteByBatch(UserId $userId, string $batchId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM imported_transactions WHERE user_id = :userId AND import_batch_id = :batchId',
            [
                'userId' => $userId->toString(),
                'batchId' => $batchId,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): ImportedTransaction
    {
        return new ImportedTransaction(
            id: TransactionId::fromString($row['id']),
            userId: UserId::fromString($row['user_id']),
            importBatchId: $row['import_batch_id'],
            broker: BrokerId::of($row['broker_id']),
            isin: $row['isin'] !== null ? ISIN::fromString($row['isin']) : null,
            symbol: $row['symbol'],
            transactionType: $row['transaction_type'],
            date: new \DateTimeImmutable($row['transaction_date'], PolishTimezone::get()),
            quantity: BigDecimal::of($row['quantity']),
            pricePerUnit: Money::of($row['price_amount'], CurrencyCode::from($row['price_currency'])),
            commission: Money::of($row['commission_amount'], CurrencyCode::from($row['commission_currency'])),
            description: $row['description'],
            contentHash: $row['content_hash'],
            createdAt: new \DateTimeImmutable($row['created_at'], PolishTimezone::get()),
        );
    }
}
