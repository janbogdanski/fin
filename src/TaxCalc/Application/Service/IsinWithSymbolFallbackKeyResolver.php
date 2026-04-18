<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\TaxCalc\Application\Port\InstrumentKeyResolverInterface;

/**
 * Default resolver for mixed-broker portfolios.
 *
 * Prefers ISIN (ISO 6166) when the broker provides it.
 * Falls back to the ticker symbol when ISIN is absent (e.g. XTB exports
 * only tickers like "AAPL.US" or "VWCE.DE").
 * Returns null only when both are empty — such transactions are skipped.
 *
 * Cross-broker FIFO: if the same instrument was bought via a broker that
 * provides ISIN and sold via one that only provides a symbol, the keys
 * will differ and FIFO will not match them. A per-broker strategy or an
 * ISIN-lookup enrichment step would be required to handle that case.
 */
final readonly class IsinWithSymbolFallbackKeyResolver implements InstrumentKeyResolverInterface
{
    public function resolveKey(NormalizedTransaction $tx): ?string
    {
        if ($tx->isin !== null) {
            return $tx->isin->toString();
        }

        return $tx->symbol !== '' ? $tx->symbol : null;
    }
}
