<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Shared calculation logic used by both command and query handlers.
 *
 * Temporary: until a read model / persistence for AnnualTaxCalculation is added,
 * both the write side (CalculateAnnualTaxHandler) and the read side
 * (GetTaxSummaryHandler) need to compute from source data.
 *
 * Once a read model exists, the query handler should read pre-computed data
 * and this service will only be used by the command handler.
 *
 * @see ADR-xxx (future: read model for tax calculations)
 */
final readonly class AnnualTaxCalculationService
{
    public function __construct(
        private ClosedPositionQueryPort $closedPositionQuery,
        private DividendResultQueryPort $dividendResultQuery,
    ) {
    }

    public function calculate(UserId $userId, TaxYear $taxYear): AnnualTaxCalculation
    {
        $calculation = AnnualTaxCalculation::create($userId, $taxYear);

        foreach (TaxCategory::cases() as $category) {
            $positions = $this->closedPositionQuery->findByUserYearAndCategory(
                $userId,
                $taxYear,
                $category,
            );

            if ($positions !== []) {
                $calculation->addClosedPositions($positions, $category);
            }
        }

        $dividends = $this->dividendResultQuery->findByUserAndYear($userId, $taxYear);

        foreach ($dividends as $dividend) {
            $calculation->addDividendResult($dividend);
        }

        $calculation->finalize();

        return $calculation;
    }
}
