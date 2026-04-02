<?php

declare(strict_types=1);

namespace App\BrokerImport\Domain\Model;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use Brick\Math\BigDecimal;

/**
 * Persisted representation of an imported broker transaction.
 *
 * Stored in DB after user confirms the import.
 * Source of truth for FIFO matching and tax calculations.
 *
 * Transaction type is stored as a plain string to avoid domain-to-application
 * layer coupling. Conversion to/from NormalizedTransaction DTO happens in
 * Infrastructure (DoctrineImportedTransactionRepository, DoctrineImportStorageAdapter).
 */
final readonly class ImportedTransaction
{
    public function __construct(
        public TransactionId $id,
        public UserId $userId,
        public string $importBatchId,
        public BrokerId $broker,
        public ?ISIN $isin,
        public string $symbol,
        public string $transactionType,
        public \DateTimeImmutable $date,
        public BigDecimal $quantity,
        public Money $pricePerUnit,
        public Money $commission,
        public string $description,
        public string $contentHash,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
