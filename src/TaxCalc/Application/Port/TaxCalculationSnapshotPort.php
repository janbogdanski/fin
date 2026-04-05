<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\TaxCalc\Application\Dto\TaxCalculationSnapshot;

/**
 * Output port for persisting tax calculation snapshots.
 *
 * Implementations must store the snapshot durably so it can be
 * retrieved for audit purposes independently of current calculation logic.
 */
interface TaxCalculationSnapshotPort
{
    public function save(TaxCalculationSnapshot $snapshot): void;
}
