<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
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
     * @var array<string, array{id: string, user_id: string, loss_year: int, tax_category: TaxCategory, original_amount: BigDecimal, remaining_amount: BigDecimal, created_at: \DateTimeImmutable, used_in_years: list<int>}>
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
                    usedInYears: $row['used_in_years'],
                );
            }
        }

        usort($result, static fn (PriorYearLossRow $a, PriorYearLossRow $b): int => $a->lossYear <=> $b->lossYear);

        return $result;
    }

    public function save(SavePriorYearLoss $command): void
    {
        $userId = $command->userId;
        $lossYear = $command->lossYear;
        $taxCategory = $command->taxCategory;
        $amount = $command->amount;

        $existingId = $this->findExisting($userId, $lossYear, $taxCategory);

        if ($existingId !== null) {
            $row = $this->rows[$existingId];

            if ($row['used_in_years'] !== [] && $amount->isLessThan($row['original_amount'])) {
                throw new \DomainException(
                    sprintf(
                        'Cannot reduce original amount of loss (year %d, %s) that has already been used in a tax declaration. Current: %s, proposed: %s.',
                        $lossYear,
                        $taxCategory->value,
                        $row['original_amount'],
                        $amount,
                    ),
                );
            }

            $row['original_amount'] = $amount;
            $row['remaining_amount'] = $amount;
            /** @var array{id: string, user_id: string, loss_year: int, tax_category: TaxCategory, original_amount: BigDecimal, remaining_amount: BigDecimal, created_at: \DateTimeImmutable, used_in_years: list<int>} $row */
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
            'used_in_years' => [],
        ];
    }

    public function delete(string $id, UserId $userId): void
    {
        if (
            ! isset($this->rows[$id])
            || $this->rows[$id]['user_id'] !== $userId->toString()
        ) {
            return;
        }

        if ($this->rows[$id]['used_in_years'] !== []) {
            $row = $this->rows[$id];
            throw new \DomainException(
                sprintf(
                    'Cannot delete loss (year %d, %s) that has already been used in a tax declaration (used in: %s).',
                    $row['loss_year'],
                    $row['tax_category']->value,
                    implode(', ', $row['used_in_years']),
                ),
            );
        }

        unset($this->rows[$id]);
    }

    public function markUsedInYear(
        UserId $userId,
        int $lossYear,
        TaxCategory $taxCategory,
        int $usedInYear,
    ): void {
        $existingId = $this->findExisting($userId, $lossYear, $taxCategory);

        if ($existingId === null) {
            throw new \DomainException(
                sprintf(
                    'Cannot mark non-existent loss as used (user: %s, year: %d, category: %s).',
                    $userId->toString(),
                    $lossYear,
                    $taxCategory->value,
                ),
            );
        }

        $row = $this->rows[$existingId];

        if (! in_array($usedInYear, $row['used_in_years'], true)) {
            $row['used_in_years'][] = $usedInYear;
        }

        /** @var array{id: string, user_id: string, loss_year: int, tax_category: TaxCategory, original_amount: BigDecimal, remaining_amount: BigDecimal, created_at: \DateTimeImmutable, used_in_years: list<int>} $row */
        $this->rows[$existingId] = $row;
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
