<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Doctrine;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Component\Uid\Uuid;

/**
 * Persistent import storage backed by Doctrine DBAL.
 * Converts between NormalizedTransaction DTOs and ImportedTransaction entities.
 */
final readonly class DoctrineImportStorageAdapter implements ImportStoragePort
{
    public function __construct(
        private ImportedTransactionRepositoryInterface $repository,
    ) {
    }

    public function store(UserId $userId, string $brokerId, array $transactions, string $contentHash): string
    {
        if ($transactions === []) {
            return '';
        }

        $batchId = Uuid::v7()->toRfc4122();

        $entities = array_map(
            static fn (NormalizedTransaction $tx): ImportedTransaction => self::toEntity(
                $tx,
                $userId,
                $batchId,
                $contentHash,
            ),
            $transactions,
        );

        $this->repository->saveAll($entities);

        return $batchId;
    }

    public function getAllTransactions(UserId $userId): array
    {
        $entities = $this->repository->findByUser($userId);

        return array_map(self::toNormalized(...), $entities);
    }

    public function getBrokerCount(UserId $userId): int
    {
        return $this->repository->countBrokersByUser($userId);
    }

    public function getTotalTransactionCount(UserId $userId): int
    {
        return $this->repository->countByUser($userId);
    }

    public function getClosedPositionCount(UserId $userId, int $year): int
    {
        return $this->repository->countSellsByUserAndYear($userId, $year);
    }

    public function wasAlreadyImported(UserId $userId, string $contentHash): bool
    {
        return $this->repository->existsByContentHash($userId, $contentHash);
    }

    private static function toEntity(
        NormalizedTransaction $tx,
        UserId $userId,
        string $batchId,
        string $contentHash,
    ): ImportedTransaction {
        return new ImportedTransaction(
            id: $tx->id,
            userId: $userId,
            importBatchId: $batchId,
            broker: $tx->broker,
            isin: $tx->isin,
            symbol: $tx->symbol,
            transactionType: $tx->type->value,
            date: $tx->date,
            quantity: $tx->quantity,
            pricePerUnit: $tx->pricePerUnit,
            commission: $tx->commission,
            description: $tx->description,
            contentHash: $contentHash,
            createdAt: new \DateTimeImmutable(),
        );
    }

    private static function toNormalized(ImportedTransaction $entity): NormalizedTransaction
    {
        return new NormalizedTransaction(
            id: $entity->id,
            isin: $entity->isin,
            symbol: $entity->symbol,
            type: TransactionType::from($entity->transactionType),
            date: $entity->date,
            quantity: $entity->quantity,
            pricePerUnit: $entity->pricePerUnit,
            commission: $entity->commission,
            broker: $entity->broker,
            description: $entity->description,
            rawData: [],
        );
    }
}
