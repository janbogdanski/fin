<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Adapter;

use App\Declaration\Application\Port\TaxSummaryQueryPort;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Query\GetTaxSummary;
use App\TaxCalc\Application\Query\GetTaxSummaryHandler;
use App\TaxCalc\Application\Query\TaxSummaryResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Adapts the TaxCalc query handler to the Declaration port.
 */
final readonly class GetTaxSummaryAdapter implements TaxSummaryQueryPort
{
    public function __construct(
        private GetTaxSummaryHandler $handler,
    ) {
    }

    public function getTaxSummary(UserId $userId, TaxYear $taxYear): TaxSummaryResult
    {
        return ($this->handler)(new GetTaxSummary($userId, $taxYear));
    }
}
