<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port wyjsciowy -- dostep do strat z lat poprzednich do odliczenia.
 * Implementacja (Doctrine, in-memory) w Infrastructure.
 *
 * @see art. 9 ust. 3 ustawy o PIT -- odliczanie strat z 5 lat
 */
interface PriorYearLossQueryPort
{
    /**
     * Returns available loss deduction ranges for the given user and tax year.
     *
     * Each range represents a prior-year loss that may be partially deducted.
     * The chosenAmounts (how much to actually deduct) are determined by the caller.
     *
     * @return list<LossDeductionRange>
     */
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array;
}
