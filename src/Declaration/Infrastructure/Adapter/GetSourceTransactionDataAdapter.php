<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Adapter;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Declaration\Application\Port\SourceTransactionLookupPort;
use App\Declaration\Domain\DTO\SourceTransactionSnapshot;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetSourceTransactionDataAdapter implements SourceTransactionLookupPort
{
    public function __construct(
        private ImportedTransactionRepositoryInterface $importedTransactionRepository,
    ) {
    }

    public function findByUserAndIds(UserId $userId, array $transactionIds): array
    {
        $transactions = $this->importedTransactionRepository->findByUserAndIds($userId, $transactionIds);

        return array_map(
            static fn ($transaction): SourceTransactionSnapshot => new SourceTransactionSnapshot(
                transactionId: $transaction->id->toString(),
                symbol: $transaction->symbol,
                pricePerUnit: (string) $transaction->pricePerUnit->amount(),
                priceCurrency: $transaction->pricePerUnit->currency()->value,
            ),
            $transactions,
        );
    }
}
