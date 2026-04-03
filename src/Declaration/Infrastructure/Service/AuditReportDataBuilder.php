<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Service;

use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\ClosedPositionEntry;
use App\Declaration\Domain\DTO\DividendEntry;
use App\Declaration\Domain\DTO\PriorYearLossEntry;
use App\Declaration\Infrastructure\Dto\AuditTotals;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Application\Port\PriorYearLossQueryPort;
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds AuditReportData by querying TaxCalc ports for closed positions,
 * dividends, and prior year losses.
 *
 * Infrastructure service -- bridges TaxCalc BC data into Declaration DTOs.
 */
final readonly class AuditReportDataBuilder
{
    public function __construct(
        private ClosedPositionQueryPort $closedPositionQuery,
        private DividendResultQueryPort $dividendResultQuery,
        private PriorYearLossQueryPort $priorYearLossQuery,
    ) {
    }

    public function build(
        UserId $userId,
        TaxYear $taxYear,
        string $firstName,
        string $lastName,
    ): AuditReportData {
        $closedPositions = $this->fetchClosedPositions($userId, $taxYear);
        $dividendResults = $this->dividendResultQuery->findByUserAndYear($userId, $taxYear);
        $lossRanges = $this->priorYearLossQuery->findByUserAndYear($userId, $taxYear);

        $positionEntries = array_map(self::mapClosedPosition(...), $closedPositions);
        $dividendEntries = array_map(self::mapDividend(...), $dividendResults);
        $lossEntries = array_map(self::mapLoss(...), $lossRanges);

        $totals = $this->calculateTotals($closedPositions, $dividendResults);

        return new AuditReportData(
            taxYear: $taxYear->value,
            firstName: $firstName,
            lastName: $lastName,
            closedPositions: $positionEntries,
            dividends: $dividendEntries,
            priorYearLosses: $lossEntries,
            totalProceeds: $totals->proceeds,
            totalCosts: $totals->costs,
            totalGainLoss: $totals->gainLoss,
            totalDividendGross: $totals->dividendGross,
            totalDividendWHT: $totals->dividendWHT,
            totalTax: $totals->tax,
        );
    }

    /**
     * @return list<ClosedPosition>
     */
    private function fetchClosedPositions(UserId $userId, TaxYear $taxYear): array
    {
        $all = [];

        foreach (TaxCategory::cases() as $category) {
            $positions = $this->closedPositionQuery->findByUserYearAndCategory(
                $userId,
                $taxYear,
                $category,
            );
            $all = [...$all, ...$positions];
        }

        return $all;
    }

    private static function mapClosedPosition(ClosedPosition $pos): ClosedPositionEntry
    {
        return new ClosedPositionEntry(
            isin: $pos->isin->toString(),
            buyDate: $pos->buyDate->format('Y-m-d'),
            sellDate: $pos->sellDate->format('Y-m-d'),
            quantity: self::format($pos->quantity),
            costBasisPLN: self::format($pos->costBasisPLN),
            proceedsPLN: self::format($pos->proceedsPLN),
            buyCommissionPLN: self::format($pos->buyCommissionPLN),
            sellCommissionPLN: self::format($pos->sellCommissionPLN),
            gainLossPLN: self::format($pos->gainLossPLN),
            buyNBPRate: self::format($pos->buyNBPRate->rate()),
            sellNBPRate: self::format($pos->sellNBPRate->rate()),
            sellBroker: $pos->sellBroker->toString(),
        );
    }

    private static function mapDividend(DividendTaxResult $div): DividendEntry
    {
        return new DividendEntry(
            payDate: $div->nbpRate->effectiveDate(),
            instrumentName: sprintf('Dywidendy -- %s', $div->sourceCountry->value),
            countryCode: $div->sourceCountry,
            grossAmountPLN: self::format($div->grossDividendPLN->amount()),
            whtPLN: self::format($div->whtPaidPLN->amount()),
            netAmountPLN: self::format($div->grossDividendPLN->amount()->minus($div->whtPaidPLN->amount())),
            nbpRate: self::format($div->nbpRate->rate()),
            nbpTableNumber: $div->nbpRate->tableNumber(),
        );
    }

    private static function mapLoss(LossDeductionRange $loss): PriorYearLossEntry
    {
        return new PriorYearLossEntry(
            year: $loss->lossYear->value,
            amount: self::format($loss->originalAmount),
            deducted: self::format($loss->maxDeductionThisYear),
        );
    }

    /**
     * @param list<ClosedPosition> $positions
     * @param list<DividendTaxResult> $dividends
     */
    private function calculateTotals(array $positions, array $dividends): AuditTotals
    {
        $proceeds = BigDecimal::zero();
        $costs = BigDecimal::zero();
        $gainLoss = BigDecimal::zero();

        foreach ($positions as $pos) {
            $proceeds = $proceeds->plus($pos->proceedsPLN);
            $costs = $costs->plus($pos->costBasisPLN)
                ->plus($pos->buyCommissionPLN)
                ->plus($pos->sellCommissionPLN);
            $gainLoss = $gainLoss->plus($pos->gainLossPLN);
        }

        $dividendGross = BigDecimal::zero();
        $dividendWHT = BigDecimal::zero();
        $dividendTax = BigDecimal::zero();

        foreach ($dividends as $div) {
            $dividendGross = $dividendGross->plus($div->grossDividendPLN->amount());
            $dividendWHT = $dividendWHT->plus($div->whtPaidPLN->amount());
            $dividendTax = $dividendTax->plus($div->polishTaxDue->amount());
        }

        // Total tax = 19% of equity gain (if positive) + dividend tax due
        $equityTax = $gainLoss->isPositive()
            ? $gainLoss->multipliedBy('0.19')->toScale(0, RoundingMode::DOWN)
            : BigDecimal::zero();
        $totalTax = $equityTax->plus($dividendTax);

        return new AuditTotals(
            proceeds: self::format($proceeds),
            costs: self::format($costs),
            gainLoss: self::format($gainLoss),
            dividendGross: self::format($dividendGross),
            dividendWHT: self::format($dividendWHT),
            tax: self::format($totalTax),
        );
    }

    private static function format(BigDecimal $amount): string
    {
        return $amount->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
