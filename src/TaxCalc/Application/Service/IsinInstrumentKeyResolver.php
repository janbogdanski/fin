<?php

declare(strict_types=1);

namespace App\TaxCalc\Application\Service;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\TaxCalc\Application\Port\InstrumentKeyResolverInterface;

/**
 * Resolves instrument key from ISIN only.
 *
 * Suitable for brokers that always provide a valid ISO 6166 ISIN (e.g. IBKR, Degiro).
 * Transactions without an ISIN are treated as unresolvable and will be skipped by the
 * FIFO pipeline.
 */
final readonly class IsinInstrumentKeyResolver implements InstrumentKeyResolverInterface
{
    public function resolveKey(NormalizedTransaction $tx): ?string
    {
        return $tx->isin?->toString();
    }
}
