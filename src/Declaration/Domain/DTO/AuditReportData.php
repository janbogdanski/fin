<?php

declare(strict_types=1);

namespace App\Declaration\Domain\DTO;

/**
 * Dane wejsciowe do generatora raportu audytowego (HTML -> PDF).
 *
 * @param list<ClosedPositionEntry> $closedPositions
 * @param list<DividendEntry>       $dividends
 * @param list<PriorYearLoss>       $priorYearLosses
 */
final readonly class AuditReportData
{
    /**
     * @param list<ClosedPositionEntry> $closedPositions
     * @param list<DividendEntry>       $dividends
     * @param list<PriorYearLoss>       $priorYearLosses
     */
    public function __construct(
        public int $taxYear,
        public string $firstName,
        public string $lastName,
        public array $closedPositions,
        public array $dividends,
        public array $priorYearLosses,
        public string $totalProceeds,
        public string $totalCosts,
        public string $totalGainLoss,
        public string $totalDividendGross,
        public string $totalDividendWHT,
        public string $totalTax,
    ) {
    }
}
