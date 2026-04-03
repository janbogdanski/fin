<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Dto\PriorYearLossRow;

/**
 * CRUD port for prior year losses management (controller-facing).
 *
 * Separated from PriorYearLossQueryPort which serves the read-side
 * tax calculation pipeline (LossDeductionRange VOs).
 */
interface PriorYearLossCrudPort
{
    /**
     * @return list<PriorYearLossRow>
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
