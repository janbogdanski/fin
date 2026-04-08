<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Repository;

use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\ValueObject\TaxYear;

interface TaxPositionLedgerRepositoryInterface
{
    public function save(TaxPositionLedger $ledger): void;

    public function findByUserAndISIN(UserId $userId, ISIN $isin): ?TaxPositionLedger;

    public function deleteClosedPositionsForUserAndYear(UserId $userId, TaxYear $taxYear): void;
}
