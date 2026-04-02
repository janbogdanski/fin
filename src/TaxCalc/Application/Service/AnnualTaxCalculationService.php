<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;

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
        private PriorYearLossQueryPort $priorYearLossQuery,
    ) {
    }

    // TODO: P2-033 — Performance optimization for scale:
    //  Instead of loading all ClosedPosition entities into memory, add an aggregate SQL query
    //  to ClosedPositionQueryPort (e.g. sumByUserYearAndCategory()) that returns sum DTOs
    //  (totalProceeds, totalCosts, totalGainLoss) computed in the database.
    //  This avoids hydrating thousands of rows when only totals are needed by AnnualTaxCalculation.
    //  Keep the current approach as fallback for audit/detail views.
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

        // Apply prior year losses (art. 9 ust. 3 ustawy o PIT)
        // Default strategy: apply maximum allowed deduction, clamped to current gain
        // to prevent wasting deduction rights when gain is smaller than available deduction.
        $lossRanges = $this->priorYearLossQuery->findByUserAndYear($userId, $taxYear);

        if ($lossRanges !== []) {
            $equityGain = BigDecimal::max($calculation->equityGainLoss(), BigDecimal::zero());
            $cryptoGain = BigDecimal::max($calculation->cryptoGainLoss(), BigDecimal::zero());

            $equityUsed = BigDecimal::zero();
            $cryptoUsed = BigDecimal::zero();

            $chosenAmounts = [];

            foreach ($lossRanges as $range) {
                if ($range->taxCategory === TaxCategory::CRYPTO) {
                    $available = $cryptoGain->minus($cryptoUsed);
                    $chosen = BigDecimal::min($range->maxDeductionThisYear, $available);
                    $chosen = BigDecimal::max($chosen, BigDecimal::zero());
                    $cryptoUsed = $cryptoUsed->plus($chosen);
                } else {
                    $available = $equityGain->minus($equityUsed);
                    $chosen = BigDecimal::min($range->maxDeductionThisYear, $available);
                    $chosen = BigDecimal::max($chosen, BigDecimal::zero());
                    $equityUsed = $equityUsed->plus($chosen);
                }

                $chosenAmounts[] = $chosen;
            }

            $calculation->applyPriorYearLosses($lossRanges, $chosenAmounts);
        }

        $calculation->finalize();

        return $calculation;
    }
}
