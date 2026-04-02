<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\Policy\LossCarryForwardPolicy;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\PriorYearLoss;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL implementation of PriorYearLossQueryPort.
 *
 * Reads prior_year_losses table, converts rows to PriorYearLoss VOs,
 * then delegates to LossCarryForwardPolicy to compute LossDeductionRange.
 * Expired and fully-used losses are filtered out (null from policy).
 */
final readonly class DoctrinePriorYearLossQueryAdapter implements PriorYearLossQueryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<LossDeductionRange>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT loss_year, tax_category, original_amount, remaining_amount
                FROM prior_year_losses
                WHERE user_id = :userId
                ORDER BY loss_year ASC
            SQL,
            [
                'userId' => $userId->toString(),
            ],
        );

        $ranges = [];

        foreach ($rows as $row) {
            $loss = new PriorYearLoss(
                taxYear: TaxYear::of((int) $row['loss_year']),
                taxCategory: TaxCategory::from((string) $row['tax_category']),
                originalAmount: BigDecimal::of((string) $row['original_amount']),
                remainingAmount: BigDecimal::of((string) $row['remaining_amount']),
            );

            $range = LossCarryForwardPolicy::calculateRange($loss, $taxYear);

            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }
}
