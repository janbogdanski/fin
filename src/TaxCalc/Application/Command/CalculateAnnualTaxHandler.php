<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Command;

use App\TaxCalc\Application\Port\ClosedPositionQueryPort;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\ValueObject\TaxCategory;

/**
 * Handler — orchestrates annual tax calculation.
 *
 * Loads ClosedPositions and DividendTaxResults from ports,
 * builds the AnnualTaxCalculation aggregate, finalizes it.
 *
 * Persistence (Doctrine) will be added later — for now returns the aggregate.
 */
final readonly class CalculateAnnualTaxHandler
{
    public function __construct(
        private ClosedPositionQueryPort $closedPositionQuery,
        private DividendResultQueryPort $dividendResultQuery,
    ) {
    }

    public function __invoke(CalculateAnnualTax $command): AnnualTaxCalculation
    {
        $calculation = AnnualTaxCalculation::create($command->userId, $command->taxYear);

        // Load and aggregate closed positions per tax category
        foreach (TaxCategory::cases() as $category) {
            $positions = $this->closedPositionQuery->findByUserYearAndCategory(
                $command->userId,
                $command->taxYear,
                $category,
            );

            if ($positions !== []) {
                $calculation->addClosedPositions($positions, $category);
            }
        }

        // Load and aggregate dividend results
        $dividends = $this->dividendResultQuery->findByUserAndYear(
            $command->userId,
            $command->taxYear,
        );

        foreach ($dividends as $dividend) {
            $calculation->addDividendResult($dividend);
        }

        // Prior year losses — skipped for now (requires user input / separate flow)

        $calculation->finalize();

        return $calculation;
    }
}
