<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\Policy\LossCarryForwardPolicy;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * In-memory implementation of PriorYearLossQueryPort for testing.
 *
 * Wraps InMemoryPriorYearLossCrud and applies LossCarryForwardPolicy,
 * mirroring the behavior of DoctrinePriorYearLossQueryAdapter.
 */
final class InMemoryPriorYearLossQueryAdapter implements PriorYearLossQueryPort
{
    public function __construct(
        private readonly InMemoryPriorYearLossCrud $crud,
    ) {
    }

    /**
     * @return list<LossDeductionRange>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        $ranges = [];

        foreach ($this->crud->findByUser($userId) as $row) {
            $loss = new PriorYearLoss(
                taxYear: TaxYear::of($row->lossYear),
                taxCategory: $row->taxCategory,
                originalAmount: $row->originalAmount,
                remainingAmount: $row->remainingAmount,
            );

            $range = LossCarryForwardPolicy::calculateRange($loss, $taxYear);

            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }
}
