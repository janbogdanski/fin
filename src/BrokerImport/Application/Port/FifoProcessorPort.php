<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Service\LedgerProcessingResult;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port for triggering FIFO matching after import.
 *
 * The BrokerImport module orchestrates the import pipeline but delegates
 * FIFO calculation to TaxCalc via this interface, avoiding a direct
 * dependency on TaxCalc's concrete service classes.
 */
interface FifoProcessorPort
{
    /**
     * @param list<NormalizedTransaction> $transactions all imported transactions (any year)
     */
    public function process(
        array $transactions,
        UserId $userId,
        TaxYear $taxYear,
        bool $persist = false,
    ): LedgerProcessingResult;
}
