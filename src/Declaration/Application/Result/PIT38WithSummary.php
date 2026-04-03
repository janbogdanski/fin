<?php

declare(strict_types=1);

namespace App\Declaration\Application\Result;

use App\Declaration\Domain\DTO\PIT38Data;
use App\TaxCalc\Application\Query\TaxSummaryResult;

/**
 * Successfully built PIT-38 data with the underlying tax summary.
 */
final readonly class PIT38WithSummary implements DeclarationResult
{
    public function __construct(
        public PIT38Data $pit38,
        public TaxSummaryResult $summary,
    ) {
    }
}
