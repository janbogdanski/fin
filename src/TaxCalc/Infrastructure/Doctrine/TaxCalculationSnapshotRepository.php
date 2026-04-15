<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\Port\GdprDataErasurePort;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Dto\TaxCalculationSnapshot;
use App\TaxCalc\Application\Port\TaxCalculationSnapshotPort;
use Doctrine\DBAL\Connection;

/**
 * DBAL-backed implementation of TaxCalculationSnapshotPort.
 *
 * Uses a simple INSERT — no ORM mapping needed for a write-only audit record.
 */
final readonly class TaxCalculationSnapshotRepository implements TaxCalculationSnapshotPort, GdprDataErasurePort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function deleteByUser(UserId $userId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tax_calculation_snapshots WHERE user_id = :userId',
            [
                'userId' => $userId->toString(),
            ],
        );
    }

    public function save(TaxCalculationSnapshot $snapshot): void
    {
        $this->connection->insert('tax_calculation_snapshots', [
            'id' => $snapshot->id,
            'user_id' => $snapshot->userId,
            'tax_year' => $snapshot->taxYear,
            'generated_at' => $snapshot->generatedAt->format('Y-m-d H:i:s'),
            'equity_gain_loss' => $snapshot->equityGainLoss,
            'equity_tax_base' => $snapshot->equityTaxBase,
            'equity_tax_due' => $snapshot->equityTaxDue,
            'prior_losses_applied' => $snapshot->priorLossesApplied,
            'dividend_income' => $snapshot->dividendIncome,
            'dividend_tax_due' => $snapshot->dividendTaxDue,
            'xml_sha256' => $snapshot->xmlSha256,
        ]);
    }
}
