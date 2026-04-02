<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\TaxCalc\Domain\Model\ClosedPosition;

/**
 * Result of processing imported transactions through FIFO ledger.
 */
final readonly class LedgerProcessingResult
{
    /**
     * @param list<ClosedPosition> $closedPositions positions closed in the target tax year
     * @param list<string> $errors human-readable error messages (e.g. sell without matching buy)
     */
    public function __construct(
        public array $closedPositions,
        public array $errors,
    ) {
    }
}
