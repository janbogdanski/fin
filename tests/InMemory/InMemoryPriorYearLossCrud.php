<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of PriorYearLossCrudPort for testing.
 *
 * Mirrors the Doctrine DBAL behavior: save() performs upsert on
 * (user_id, loss_year, tax_category) composite key.
 */
final class InMemoryPriorYearLossCrud implements PriorYearLossCrudPort
{
    /**
     * @var array<string, array{id: string, user_id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string}>
     */
    private array $rows = [];

    public function findByUser(UserId $userId): array
    {
        $result = [];

        foreach ($this->rows as $row) {
            if ($row['user_id'] === $userId->toString()) {
                $result[] = [
                    'id' => $row['id'],
                    'loss_year' => $row['loss_year'],
                    'tax_category' => $row['tax_category'],
                    'original_amount' => $row['original_amount'],
                    'remaining_amount' => $row['remaining_amount'],
                    'created_at' => $row['created_at'],
                ];
            }
        }

        usort($result, static fn (array $a, array $b): int => $a['loss_year'] <=> $b['loss_year']);

        return $result;
    }

    public function save(
        UserId $userId,
        int $lossYear,
        string $taxCategory,
        string $amount,
    ): void {
        $existingId = $this->findExisting($userId, $lossYear, $taxCategory);

        if ($existingId !== null) {
            $row = $this->rows[$existingId];
            $row['original_amount'] = $amount;
            $row['remaining_amount'] = $amount;
            /** @var array{id: string, user_id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string} $row */
            $this->rows[$existingId] = $row;

            return;
        }

        $id = Uuid::v7()->toRfc4122();

        $this->rows[$id] = [
            'id' => $id,
            'user_id' => $userId->toString(),
            'loss_year' => $lossYear,
            'tax_category' => $taxCategory,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    public function delete(string $id, UserId $userId): void
    {
        if (
            isset($this->rows[$id])
            && $this->rows[$id]['user_id'] === $userId->toString()
        ) {
            unset($this->rows[$id]);
        }
    }

    private function findExisting(UserId $userId, int $lossYear, string $taxCategory): ?string
    {
        foreach ($this->rows as $row) {
            if (
                $row['user_id'] === $userId->toString()
                && $row['loss_year'] === $lossYear
                && $row['tax_category'] === $taxCategory
            ) {
                return $row['id'];
            }
        }

        return null;
    }
}
