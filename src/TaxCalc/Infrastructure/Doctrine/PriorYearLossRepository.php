<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * DBAL repository for prior_year_losses table (CRUD operations).
 *
 * Separated from PriorYearLossQueryPort which only provides read-side
 * LossDeductionRange VOs for the tax calculation pipeline.
 */
final readonly class PriorYearLossRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<array{id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string}>
     */
    public function findByUser(UserId $userId): array
    {
        /** @var list<array{id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT id, loss_year, tax_category, original_amount, remaining_amount, created_at FROM prior_year_losses WHERE user_id = :userId ORDER BY loss_year ASC',
            [
                'userId' => $userId->toString(),
            ],
        );
    }

    public function save(
        UserId $userId,
        int $lossYear,
        string $taxCategory,
        string $amount,
    ): void {
        $this->connection->insert('prior_year_losses', [
            'id' => Uuid::v7()->toRfc4122(),
            'user_id' => $userId->toString(),
            'loss_year' => $lossYear,
            'tax_category' => $taxCategory,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
        ]);
    }

    public function delete(string $id, UserId $userId): void
    {
        $this->connection->delete('prior_year_losses', [
            'id' => $id,
            'user_id' => $userId->toString(),
        ]);
    }
}
