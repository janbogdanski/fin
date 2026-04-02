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
use App\TaxCalc\Domain\Model\OpenPosition;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Repository\TaxPositionLedgerRepositoryInterface;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Connection;

/**
 * DBAL-based repository.
 *
 * Domain entities use composite Value Objects (NBPRate, TransactionId, etc.)
 * that don't map cleanly to Doctrine ORM. Using DBAL gives full control
 * over hydration without polluting the domain with persistence concerns.
 */
final readonly class DoctrineTaxPositionLedgerRepository implements TaxPositionLedgerRepositoryInterface
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(TaxPositionLedger $ledger): void
    {
        $this->connection->beginTransaction();

        try {
            $ledgerId = $this->upsertLedger($ledger);
            $this->syncOpenPositions($ledgerId, $ledger);
            $this->insertClosedPositions($ledger);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function findByUserAndISIN(UserId $userId, ISIN $isin): ?TaxPositionLedger
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, isin, tax_category FROM tax_position_ledgers WHERE user_id = :userId AND isin = :isin',
            [
                'userId' => $userId->toString(),
                'isin' => $isin->toString(),
            ],
        );

        if ($row === false) {
            return null;
        }

        $openPositions = $this->loadOpenPositions((int) $row['id']);

        return TaxPositionLedger::reconstitute(
            UserId::fromString($row['user_id']),
            ISIN::fromString($row['isin']),
            TaxCategory::from($row['tax_category']),
            $openPositions,
        );
    }

    private function upsertLedger(TaxPositionLedger $ledger): int
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM tax_position_ledgers WHERE user_id = :userId AND isin = :isin',
            [
                'userId' => $ledger->userId()->toString(),
                'isin' => $ledger->isin()->toString(),
            ],
        );

        if ($existing !== false) {
            return (int) $existing;
        }

        $this->connection->insert('tax_position_ledgers', [
            'user_id' => $ledger->userId()->toString(),
            'isin' => $ledger->isin()->toString(),
            'tax_category' => $ledger->taxCategory()->value,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function syncOpenPositions(int $ledgerId, TaxPositionLedger $ledger): void
    {
        $this->connection->executeStatement(
            'DELETE FROM open_positions WHERE ledger_id = :ledgerId',
            [
                'ledgerId' => $ledgerId,
            ],
        );

        $positions = $ledger->openPositions();
        if ($positions === []) {
            return;
        }

        $columns = [
            'ledger_id', 'transaction_id', 'date', 'original_quantity',
            'remaining_quantity', 'cost_per_unit_pln', 'commission_per_unit_pln',
            'broker', 'nbp_rate_currency', 'nbp_rate_value', 'nbp_rate_date', 'nbp_rate_table',
        ];

        foreach (array_chunk($positions, self::BATCH_SIZE) as $batch) {
            $placeholders = [];
            $params = [];
            $paramIndex = 0;

            foreach ($batch as $position) {
                $row = [
                    $ledgerId,
                    $position->transactionId->toString(),
                    $position->date->format('Y-m-d H:i:s'),
                    (string) $position->originalQuantity,
                    (string) $position->remainingQuantity(),
                    (string) $position->costPerUnitPLN,
                    (string) $position->commissionPerUnitPLN,
                    $position->broker->toString(),
                    $position->nbpRate->currency()->value,
                    (string) $position->nbpRate->rate(),
                    $position->nbpRate->effectiveDate()->format('Y-m-d'),
                    $position->nbpRate->tableNumber(),
                ];

                $rowPlaceholders = [];
                foreach ($row as $value) {
                    $paramName = 'p' . $paramIndex++;
                    $rowPlaceholders[] = ':' . $paramName;
                    $params[$paramName] = $value;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }

            $sql = sprintf(
                'INSERT INTO open_positions (%s) VALUES %s',
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $this->connection->executeStatement($sql, $params);
        }
    }

    private function insertClosedPositions(TaxPositionLedger $ledger): void
    {
        $closedPositions = $ledger->flushNewClosedPositions();
        if ($closedPositions === []) {
            return;
        }

        $columns = [
            'buy_transaction_id', 'sell_transaction_id', 'isin', 'quantity',
            'cost_basis_pln', 'proceeds_pln', 'buy_commission_pln', 'sell_commission_pln',
            'gain_loss_pln', 'buy_date', 'sell_date',
            'buy_nbp_rate_currency', 'buy_nbp_rate_value', 'buy_nbp_rate_date', 'buy_nbp_rate_table',
            'sell_nbp_rate_currency', 'sell_nbp_rate_value', 'sell_nbp_rate_date', 'sell_nbp_rate_table',
            'buy_broker', 'sell_broker',
        ];

        foreach (array_chunk($closedPositions, self::BATCH_SIZE) as $batch) {
            $placeholders = [];
            $params = [];
            $paramIndex = 0;

            foreach ($batch as $closed) {
                $row = [
                    $closed->buyTransactionId->toString(),
                    $closed->sellTransactionId->toString(),
                    $closed->isin->toString(),
                    (string) $closed->quantity,
                    (string) $closed->costBasisPLN,
                    (string) $closed->proceedsPLN,
                    (string) $closed->buyCommissionPLN,
                    (string) $closed->sellCommissionPLN,
                    (string) $closed->gainLossPLN,
                    $closed->buyDate->format('Y-m-d H:i:s'),
                    $closed->sellDate->format('Y-m-d H:i:s'),
                    $closed->buyNBPRate->currency()->value,
                    (string) $closed->buyNBPRate->rate(),
                    $closed->buyNBPRate->effectiveDate()->format('Y-m-d'),
                    $closed->buyNBPRate->tableNumber(),
                    $closed->sellNBPRate->currency()->value,
                    (string) $closed->sellNBPRate->rate(),
                    $closed->sellNBPRate->effectiveDate()->format('Y-m-d'),
                    $closed->sellNBPRate->tableNumber(),
                    $closed->buyBroker->toString(),
                    $closed->sellBroker->toString(),
                ];

                $rowPlaceholders = [];
                foreach ($row as $value) {
                    $paramName = 'p' . $paramIndex++;
                    $rowPlaceholders[] = ':' . $paramName;
                    $params[$paramName] = $value;
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }

            $sql = sprintf(
                'INSERT INTO closed_positions (%s) VALUES %s',
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $this->connection->executeStatement($sql, $params);
        }
    }

    /**
     * @return list<OpenPosition>
     */
    private function loadOpenPositions(int $ledgerId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM open_positions WHERE ledger_id = :ledgerId ORDER BY date ASC',
            [
                'ledgerId' => $ledgerId,
            ],
        );

        $positions = [];

        foreach ($rows as $row) {
            $nbpRate = NBPRate::create(
                CurrencyCode::from($row['nbp_rate_currency']),
                BigDecimal::of($row['nbp_rate_value']),
                new \DateTimeImmutable($row['nbp_rate_date'], PolishTimezone::get()),
                $row['nbp_rate_table'],
            );

            $positions[] = new OpenPosition(
                transactionId: TransactionId::fromString($row['transaction_id']),
                date: new \DateTimeImmutable($row['date'], PolishTimezone::get()),
                originalQuantity: BigDecimal::of($row['original_quantity']),
                remainingQuantity: BigDecimal::of($row['remaining_quantity']),
                costPerUnitPLN: BigDecimal::of($row['cost_per_unit_pln']),
                commissionPerUnitPLN: BigDecimal::of($row['commission_per_unit_pln']),
                nbpRate: $nbpRate,
                broker: BrokerId::of($row['broker']),
            );
        }

        return $positions;
    }
}
