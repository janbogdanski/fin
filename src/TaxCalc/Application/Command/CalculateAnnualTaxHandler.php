<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Command;

use App\TaxCalc\Application\Service\AnnualTaxCalculationService;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;

/**
 * Handler -- orchestrates annual tax calculation (write side).
 *
 * Delegates to AnnualTaxCalculationService for shared logic.
 * Persistence (Doctrine) will be added later -- for now returns the aggregate.
 */
final readonly class CalculateAnnualTaxHandler
{
    public function __construct(
        private AnnualTaxCalculationService $calculationService,
    ) {
    }

    public function __invoke(CalculateAnnualTax $command): AnnualTaxCalculation
    {
        return $this->calculationService->calculate($command->userId, $command->taxYear);
    }
}
