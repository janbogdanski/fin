<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Dto;

/**
 * Computed totals for the audit report.
 * All values are formatted strings (2 decimal places) for display.
 */
final readonly class AuditTotals
{
    public function __construct(
        public string $proceeds,
        public string $costs,
        public string $gainLoss,
        public string $dividendGross,
        public string $dividendWHT,
        public string $tax,
    ) {
    }
}
