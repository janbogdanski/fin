<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Dto\PriorYearLossRow;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
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
     * @var array<string, array{id: string, user_id: string, loss_year: int, tax_category: TaxCategory, original_amount: BigDecimal, remaining_amount: BigDecimal, created_at: \DateTimeImmutable}>
     */
    private array $rows = [];

    /**
     * @return list<PriorYearLossRow>
     */
    public function findByUser(UserId $userId): array
    {
        $result = [];

        foreach ($this->rows as $row) {
            if ($row['user_id'] === $userId->toString()) {
                $result[] = new PriorYearLossRow(
                    id: $row['id'],
                    lossYear: $row['loss_year'],
                    taxCategory: $row['tax_category'],
                    originalAmount: $row['original_amount'],
                    remainingAmount: $row['remaining_amount'],
                    createdAt: $row['created_at'],
                );
            }
        }

        usort($result, static fn (PriorYearLossRow $a, PriorYearLossRow $b): int => $a->lossYear <=> $b->lossYear);

        return $result;
    }

    public function save(
        UserId $userId,
        int $lossYear,
        TaxCategory $taxCategory,
        BigDecimal $amount,
    ): void {
        $existingId = $this->findExisting($userId, $lossYear, $taxCategory);

        if ($existingId !== null) {
            $row = $this->rows[$existingId];
            $row['original_amount'] = $amount;
            $row['remaining_amount'] = $amount;
            /** @var array{id: string, user_id: string, loss_year: int, tax_category: TaxCategory, original_amount: BigDecimal, remaining_amount: BigDecimal, created_at: \DateTimeImmutable} $row */
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
            'created_at' => new \DateTimeImmutable(),
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

    private function findExisting(UserId $userId, int $lossYear, TaxCategory $taxCategory): ?string
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
