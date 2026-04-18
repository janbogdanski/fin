<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\TaxCalc\Application\Port\InstrumentKeyResolverInterface;

/**
 * Resolves instrument key from the raw ticker symbol only.
 *
 * Suitable for brokers that do not export ISINs but always provide a stable ticker
 * symbol (e.g. XTB exports "AAPL.US"). Transactions with an empty symbol are treated
 * as unresolvable and will be skipped by the FIFO pipeline.
 */
final readonly class SymbolInstrumentKeyResolver implements InstrumentKeyResolverInterface
{
    public function resolveKey(NormalizedTransaction $tx): ?string
    {
        return $tx->symbol !== '' ? $tx->symbol : null;
    }
}
