<?php

declare(strict_types=1);

namespace App\Dashboard\Infrastructure\Service;

use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\Shared\Domain\ValueObject\UserId;
use Brick\Math\BigDecimal;

final readonly class SourceTransactionLookup
{
    public function __construct(
        private ImportedTransactionRepositoryInterface $importedTransactionRepository,
    ) {
    }

    /**
     * @param list<string> $transactionIds
     *
     * @return array<string, array{symbol: string, price: string, currency: string}>
     */
    public function findByUserAndIds(UserId $userId, array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $lookup = [];

        foreach ($this->importedTransactionRepository->findByUserAndIds($userId, array_values(array_unique($transactionIds))) as $transaction) {
            $lookup[$transaction->id->toString()] = [
                'symbol' => $transaction->symbol,
                'price' => $this->formatDecimal((string) $transaction->pricePerUnit->amount()),
                'currency' => $transaction->pricePerUnit->currency()->value,
            ];
        }

        return $lookup;
    }

    private function formatDecimal(string $value): string
    {
        $normalized = rtrim(rtrim(BigDecimal::of($value)->__toString(), '0'), '.');

        if (! str_contains($normalized, '.')) {
            return $normalized . '.00';
        }

        [$whole, $fraction] = explode('.', $normalized, 2);

        if (strlen($fraction) === 1) {
            return $whole . '.' . $fraction . '0';
        }

        return $normalized;
    }
}
