<?php

declare(strict_types=1);

namespace App\Declaration\Infrastructure\Dto;

use Brick\Math\BigDecimal;

/**
 * Computed totals for the audit report.
 * Values are BigDecimal — formatting to string happens at the presentation layer.
 */
final readonly class AuditTotals
{
    public function __construct(
        public BigDecimal $proceeds,
        public BigDecimal $costs,
        public BigDecimal $gainLoss,
        public BigDecimal $dividendGross,
        public BigDecimal $dividendWHT,
        public BigDecimal $tax,
    ) {
    }
}
