<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Port;

use App\BrokerImport\Application\DTO\NormalizedTransaction;

/**
 * Strategy for resolving the grouping key used in FIFO instrument matching.
 *
 * Different brokers provide different identifiers:
 *   - ISIN (ISO 6166) — standard, preferred (IBKR, Degiro, Revolut)
 *   - Symbol / ticker — fallback when ISIN is absent (XTB: "AAPL.US", "VWCE.DE")
 *
 * Each implementation defines how transactions should be grouped into
 * per-instrument buckets before FIFO matching begins.
 *
 * Returns null when the transaction cannot be assigned a usable key
 * (e.g. no ISIN and no symbol) — the caller must skip such transactions.
 */
interface InstrumentKeyResolverInterface
{
    public function resolveKey(NormalizedTransaction $tx): ?string;
}
