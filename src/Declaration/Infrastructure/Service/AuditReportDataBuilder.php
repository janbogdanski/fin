<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Service;

use App\Declaration\Application\Port\SourceTransactionLookupPort;
use App\Declaration\Application\Port\TaxSummaryQueryPort;
use App\Declaration\Domain\DTO\AuditReportData;
use App\Declaration\Domain\DTO\ClosedPositionEntry;
use App\Declaration\Domain\DTO\DividendEntry;
use App\Declaration\Domain\DTO\PriorYearLossEntry;
use App\Declaration\Domain\DTO\SourceTransactionSnapshot;
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
        private TaxSummaryQueryPort $taxSummaryQuery,
        private SourceTransactionLookupPort $sourceTransactionLookup,
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
        $taxSummary = $this->taxSummaryQuery->getTaxSummary($userId, $taxYear);
        $transactionLookup = $this->buildTransactionLookup($userId, $closedPositions);

        $positionEntries = array_map(
            fn (ClosedPosition $position): ClosedPositionEntry => $this->mapClosedPosition($position, $transactionLookup),
            $closedPositions,
        );
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
            totalProceeds: self::format($totals->proceeds),
            totalCosts: self::format($totals->costs),
            totalGainLoss: self::format($totals->gainLoss),
            totalDividendGross: self::format($totals->dividendGross),
            totalDividendWHT: self::format($totals->dividendWHT),
            totalTax: self::format(BigDecimal::of($taxSummary->totalTaxDue)),
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

    /**
     * @param array<string, SourceTransactionSnapshot> $transactionLookup
     */
    private function mapClosedPosition(ClosedPosition $pos, array $transactionLookup): ClosedPositionEntry
    {
        $buyTransaction = $transactionLookup[$pos->buyTransactionId->toString()] ?? null;
        $sellTransaction = $transactionLookup[$pos->sellTransactionId->toString()] ?? null;

        return new ClosedPositionEntry(
            isin: $pos->isin->toString(),
            symbol: $this->resolveSymbol($buyTransaction, $sellTransaction, $pos),
            buyDate: $pos->buyDate->format('Y-m-d'),
            sellDate: $pos->sellDate->format('Y-m-d'),
            buyBroker: $pos->buyBroker->toString(),
            sellBroker: $pos->sellBroker->toString(),
            quantity: self::format($pos->quantity),
            buyPricePerUnit: $buyTransaction !== null ? $buyTransaction->pricePerUnit : '',
            buyPriceCurrency: $buyTransaction !== null ? $buyTransaction->priceCurrency : '',
            sellPricePerUnit: $sellTransaction !== null ? $sellTransaction->pricePerUnit : '',
            sellPriceCurrency: $sellTransaction !== null ? $sellTransaction->priceCurrency : '',
            costBasisPLN: self::format($pos->costBasisPLN),
            proceedsPLN: self::format($pos->proceedsPLN),
            buyCommissionPLN: self::format($pos->buyCommissionPLN),
            sellCommissionPLN: self::format($pos->sellCommissionPLN),
            gainLossPLN: self::format($pos->gainLossPLN),
            buyNBPRate: self::format($pos->buyNBPRate->rate()),
            sellNBPRate: self::format($pos->sellNBPRate->rate()),
        );
    }

    /**
     * @param list<ClosedPosition> $closedPositions
     *
     * @return array<string, SourceTransactionSnapshot>
     */
    private function buildTransactionLookup(UserId $userId, array $closedPositions): array
    {
        if ($closedPositions === []) {
            return [];
        }

        $transactionIds = [];

        foreach ($closedPositions as $position) {
            $transactionIds[] = $position->buyTransactionId->toString();
            $transactionIds[] = $position->sellTransactionId->toString();
        }

        $transactions = $this->sourceTransactionLookup->findByUserAndIds(
            $userId,
            array_values(array_unique($transactionIds)),
        );

        $lookup = [];

        foreach ($transactions as $transaction) {
            $lookup[$transaction->transactionId] = $transaction;
        }

        return $lookup;
    }

    private function resolveSymbol(
        ?SourceTransactionSnapshot $buyTransaction,
        ?SourceTransactionSnapshot $sellTransaction,
        ClosedPosition $position,
    ): string {
        $buySymbol = $buyTransaction !== null ? $buyTransaction->symbol : '';
        if ($buySymbol !== '') {
            return $buySymbol;
        }

        $sellSymbol = $sellTransaction !== null ? $sellTransaction->symbol : '';
        if ($sellSymbol !== '') {
            return $sellSymbol;
        }

        return $position->isin->toString();
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
        foreach ($dividends as $div) {
            $dividendGross = $dividendGross->plus($div->grossDividendPLN->amount());
            $dividendWHT = $dividendWHT->plus($div->whtPaidPLN->amount());
        }

        return new AuditTotals(
            proceeds: $proceeds,
            costs: $costs,
            gainLoss: $gainLoss,
            dividendGross: $dividendGross,
            dividendWHT: $dividendWHT,
        );
    }

    private static function format(BigDecimal $amount): string
    {
        return $amount->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
