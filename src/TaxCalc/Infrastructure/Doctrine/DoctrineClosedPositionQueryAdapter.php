<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\PolishTimezone;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

final readonly class DoctrineClosedPositionQueryAdapter implements ClosedPositionQueryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findByUserYearAndCategory(
        UserId $userId,
        TaxYear $taxYear,
        TaxCategory $category,
    ): array {
        $yearStart = sprintf('%d-01-01 00:00:00', $taxYear->value);
        $yearEnd = sprintf('%d-12-31 23:59:59', $taxYear->value);

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT * FROM closed_positions
                WHERE user_id = :userId
                  AND tax_category = :category
                  AND sell_date >= :yearStart
                  AND sell_date <= :yearEnd
                ORDER BY sell_date ASC
            SQL,
            [
                'userId' => $userId->toString(),
                'category' => $category->value,
                'yearStart' => $yearStart,
                'yearEnd' => $yearEnd,
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    public function countByUserAndYear(UserId $userId, TaxYear $taxYear): int
    {
        $yearStart = sprintf('%d-01-01 00:00:00', $taxYear->value);
        $yearEnd = sprintf('%d-12-31 23:59:59', $taxYear->value);

        $count = $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM closed_positions
                WHERE user_id = :userId
                  AND sell_date >= :yearStart
                  AND sell_date <= :yearEnd
            SQL,
            [
                'userId' => $userId->toString(),
                'yearStart' => $yearStart,
                'yearEnd' => $yearEnd,
            ],
        );

        return (int) $count;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): ClosedPosition
    {
        return new ClosedPosition(
            buyTransactionId: TransactionId::fromString($row['buy_transaction_id']),
            sellTransactionId: TransactionId::fromString($row['sell_transaction_id']),
            isin: ISIN::fromString($row['isin']),
            quantity: BigDecimal::of($row['quantity']),
            costBasisPLN: BigDecimal::of($row['cost_basis_pln']),
            proceedsPLN: BigDecimal::of($row['proceeds_pln']),
            buyCommissionPLN: BigDecimal::of($row['buy_commission_pln']),
            sellCommissionPLN: BigDecimal::of($row['sell_commission_pln']),
            gainLossPLN: BigDecimal::of($row['gain_loss_pln']),
            buyDate: new \DateTimeImmutable($row['buy_date'], PolishTimezone::get()),
            sellDate: new \DateTimeImmutable($row['sell_date'], PolishTimezone::get()),
            buyNBPRate: NBPRate::create(
                CurrencyCode::from($row['buy_nbp_rate_currency']),
                BigDecimal::of($row['buy_nbp_rate_value']),
                new \DateTimeImmutable($row['buy_nbp_rate_date'], PolishTimezone::get()),
                $row['buy_nbp_rate_table'],
            ),
            sellNBPRate: NBPRate::create(
                CurrencyCode::from($row['sell_nbp_rate_currency']),
                BigDecimal::of($row['sell_nbp_rate_value']),
                new \DateTimeImmutable($row['sell_nbp_rate_date'], PolishTimezone::get()),
                $row['sell_nbp_rate_table'],
            ),
            buyBroker: BrokerId::of($row['buy_broker']),
            sellBroker: BrokerId::of($row['sell_broker']),
        );
    }
}
