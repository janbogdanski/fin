<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

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

        foreach ($ledger->openPositions() as $position) {
            $this->connection->insert('open_positions', [
                'ledger_id' => $ledgerId,
                'transaction_id' => $position->transactionId->toString(),
                'date' => $position->date->format('Y-m-d H:i:s'),
                'original_quantity' => (string) $position->originalQuantity,
                'remaining_quantity' => (string) $position->remainingQuantity(),
                'cost_per_unit_pln' => (string) $position->costPerUnitPLN,
                'commission_per_unit_pln' => (string) $position->commissionPerUnitPLN,
                'broker' => $position->broker->toString(),
                'nbp_rate_currency' => $position->nbpRate->currency()->value,
                'nbp_rate_value' => (string) $position->nbpRate->rate(),
                'nbp_rate_date' => $position->nbpRate->effectiveDate()->format('Y-m-d'),
                'nbp_rate_table' => $position->nbpRate->tableNumber(),
            ]);
        }
    }

    private function insertClosedPositions(TaxPositionLedger $ledger): void
    {
        foreach ($ledger->flushNewClosedPositions() as $closed) {
            $this->connection->insert('closed_positions', [
                'buy_transaction_id' => $closed->buyTransactionId->toString(),
                'sell_transaction_id' => $closed->sellTransactionId->toString(),
                'isin' => $closed->isin->toString(),
                'quantity' => (string) $closed->quantity,
                'cost_basis_pln' => (string) $closed->costBasisPLN,
                'proceeds_pln' => (string) $closed->proceedsPLN,
                'buy_commission_pln' => (string) $closed->buyCommissionPLN,
                'sell_commission_pln' => (string) $closed->sellCommissionPLN,
                'gain_loss_pln' => (string) $closed->gainLossPLN,
                'buy_date' => $closed->buyDate->format('Y-m-d H:i:s'),
                'sell_date' => $closed->sellDate->format('Y-m-d H:i:s'),
                'buy_nbp_rate_currency' => $closed->buyNBPRate->currency()->value,
                'buy_nbp_rate_value' => (string) $closed->buyNBPRate->rate(),
                'buy_nbp_rate_date' => $closed->buyNBPRate->effectiveDate()->format('Y-m-d'),
                'buy_nbp_rate_table' => $closed->buyNBPRate->tableNumber(),
                'sell_nbp_rate_currency' => $closed->sellNBPRate->currency()->value,
                'sell_nbp_rate_value' => (string) $closed->sellNBPRate->rate(),
                'sell_nbp_rate_date' => $closed->sellNBPRate->effectiveDate()->format('Y-m-d'),
                'sell_nbp_rate_table' => $closed->sellNBPRate->tableNumber(),
                'buy_broker' => $closed->buyBroker->toString(),
                'sell_broker' => $closed->sellBroker->toString(),
            ]);
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
                new \DateTimeImmutable($row['nbp_rate_date']),
                $row['nbp_rate_table'],
            );

            $positions[] = new OpenPosition(
                transactionId: TransactionId::fromString($row['transaction_id']),
                date: new \DateTimeImmutable($row['date']),
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
