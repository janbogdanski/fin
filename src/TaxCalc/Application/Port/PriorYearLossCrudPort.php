<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;

/**
 * CRUD port for prior year losses management (controller-facing).
 *
 * Separated from PriorYearLossQueryPort which serves the read-side
 * tax calculation pipeline (LossDeductionRange VOs).
 */
interface PriorYearLossCrudPort
{
    /**
     * @return list<array{id: string, loss_year: int, tax_category: string, original_amount: string, remaining_amount: string, created_at: string}>
     */
    public function findByUser(UserId $userId): array;

    public function save(
        UserId $userId,
        int $lossYear,
        string $taxCategory,
        string $amount,
    ): void;

    public function delete(string $id, UserId $userId): void;
}
