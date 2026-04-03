<?php

declare(strict_types=1);

namespace App\Declaration\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port for querying tax summary data.
 * Decouples Declaration BC from the concrete TaxCalc handler.
 */
interface TaxSummaryQueryPort
{
    public function getTaxSummary(UserId $userId, TaxYear $taxYear): TaxSummaryResult;
}
