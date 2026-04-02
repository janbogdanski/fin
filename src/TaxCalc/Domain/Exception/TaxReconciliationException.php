<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Exception;

use Brick\Math\BigDecimal;

/**
 * Thrown when dual-path reconciliation detects inconsistency
 * between aggregated gain/loss and recomputed (proceeds - costBasis - commissions).
 *
 * This is the financial equivalent of a double-entry bookkeeping mismatch.
 */
final class TaxReconciliationException extends \DomainException
{
    public function __construct(
        public readonly string $basket,
        public readonly BigDecimal $pathA,
        public readonly BigDecimal $pathB,
    ) {
        parent::__construct(
            "Reconciliation failed for basket '{$basket}': "
            . "path A (gainLoss sum) = {$pathA}, "
            . "path B (proceeds - costBasis - commissions) = {$pathB}.",
        );
    }
}
