<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Command\SavePriorYearLoss;
use App\TaxCalc\Application\Dto\PriorYearLossRow;
use App\TaxCalc\Application\Port\PriorYearLossCrudPort;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * DBAL repository for prior_year_losses table (CRUD operations).
 *
 * Separated from PriorYearLossQueryPort which only provides read-side
 * LossDeductionRange VOs for the tax calculation pipeline.
 */
final readonly class PriorYearLossRepository implements PriorYearLossCrudPort, GdprDataErasurePort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function deleteByUser(UserId $userId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM prior_year_losses WHERE user_id = :userId',
            [
                'userId' => $userId->toString(),
            ],
        );
    }

    /**
     * @return list<PriorYearLossRow>
     */
    public function findByUser(UserId $userId): array
    {
        /** @var list<array{id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string, used_in_years: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, loss_year, tax_category, original_amount, remaining_amount, created_at, used_in_years FROM prior_year_losses WHERE user_id = :userId ORDER BY loss_year ASC',
            [
                'userId' => $userId->toString(),
            ],
        );

        return array_map(
            static fn (array $row): PriorYearLossRow => new PriorYearLossRow(
                id: $row['id'],
                lossYear: (int) $row['loss_year'],
                taxCategory: TaxCategory::from((string) $row['tax_category']),
                originalAmount: BigDecimal::of((string) $row['original_amount']),
                remainingAmount: BigDecimal::of((string) $row['remaining_amount']),
                createdAt: new \DateTimeImmutable((string) $row['created_at']),
                usedInYears: self::decodeUsedInYears((string) $row['used_in_years']),
            ),
            $rows,
        );
    }

    public function save(SavePriorYearLoss $command): void
    {
        $userId = $command->userId;
        $lossYear = $command->lossYear;
        $taxCategory = $command->taxCategory;
        $amount = $command->amount;

        $existing = $this->findExistingRow($userId, $lossYear, $taxCategory);
        $amountStr = $amount->toScale(2)->__toString();

        if ($existing !== null) {
            $usedInYears = self::decodeUsedInYears($existing['used_in_years']);

            if ($usedInYears !== [] && $amount->isLessThan(BigDecimal::of($existing['original_amount']))) {
                throw new \DomainException(
                    sprintf(
                        'Cannot reduce original amount of loss (year %d, %s) that has already been used in a tax declaration. Current: %s, proposed: %s.',
                        $lossYear,
                        $taxCategory->value,
                        $existing['original_amount'],
                        $amountStr,
                    ),
                );
            }

            $this->connection->update('prior_year_losses', [
                'original_amount' => $amountStr,
                'remaining_amount' => $amountStr,
            ], [
                'id' => $existing['id'],
            ]);

            return;
        }

        $this->connection->insert('prior_year_losses', [
            'id' => Uuid::v7()->toRfc4122(),
            'user_id' => $userId->toString(),
            'loss_year' => $lossYear,
            'tax_category' => $taxCategory->value,
            'original_amount' => $amountStr,
            'remaining_amount' => $amountStr,
            'used_in_years' => '[]',
        ]);
    }

    public function delete(string $id, UserId $userId): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT used_in_years, loss_year, tax_category FROM prior_year_losses WHERE id = :id AND user_id = :userId',
            [
                'id' => $id,
                'userId' => $userId->toString(),
            ],
        );

        if ($row === false) {
            // Row not found or not owned by this user — silent no-op (mirrors original behavior)
            return;
        }

        /** @var array{used_in_years: string, loss_year: int, tax_category: string} $row */
        $usedInYears = self::decodeUsedInYears((string) $row['used_in_years']);

        if ($usedInYears !== []) {
            throw new \DomainException(
                sprintf(
                    'Cannot delete loss (year %d, %s) that has already been used in a tax declaration (used in: %s).',
                    (int) $row['loss_year'],
                    (string) $row['tax_category'],
                    implode(', ', $usedInYears),
                ),
            );
        }

        $this->connection->delete('prior_year_losses', [
            'id' => $id,
            'user_id' => $userId->toString(),
        ]);
    }

    /**
     * Marks a loss entry as used in the given tax year (idempotent).
     *
     * Uses a JSON array column to support multi-year carry-forward tracking.
     *
     * @throws \DomainException if the entry does not exist
     */
    public function markUsedInYear(
        UserId $userId,
        int $lossYear,
        TaxCategory $taxCategory,
        int $usedInYear,
    ): void {
        $existing = $this->findExistingRow($userId, $lossYear, $taxCategory);

        if ($existing === null) {
            throw new \DomainException(sprintf(
                'Prior year loss entry not found for user %s, year %d, category %s.',
                $userId->toString(),
                $lossYear,
                $taxCategory->value,
            ));
        }

        $usedInYears = self::decodeUsedInYears($existing['used_in_years']);

        if (in_array($usedInYear, $usedInYears, true)) {
            // Idempotent — already marked for this year
            return;
        }

        $usedInYears[] = $usedInYear;

        $this->connection->update('prior_year_losses', [
            'used_in_years' => json_encode($usedInYears, JSON_THROW_ON_ERROR),
        ], [
            'id' => $existing['id'],
        ]);
    }

    /**
     * @return array{id: string, original_amount: string, used_in_years: string}|null
     */
    private function findExistingRow(UserId $userId, int $lossYear, TaxCategory $taxCategory): ?array
    {
        /** @var array{id: string, original_amount: string, used_in_years: string}|false $result */
        $result = $this->connection->fetchAssociative(
            'SELECT id, original_amount, used_in_years FROM prior_year_losses WHERE user_id = :userId AND loss_year = :lossYear AND tax_category = :taxCategory',
            [
                'userId' => $userId->toString(),
                'lossYear' => $lossYear,
                'taxCategory' => $taxCategory->value,
            ],
        );

        return $result !== false ? $result : null;
    }

    /**
     * @return list<int>
     */
    private static function decodeUsedInYears(string $json): array
    {
        if ($json === '' || $json === 'null') {
            return [];
        }

        /** @var list<int> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
