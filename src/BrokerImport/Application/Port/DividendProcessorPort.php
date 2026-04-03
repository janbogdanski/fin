<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Port for triggering dividend tax calculation after import.
 *
 * The BrokerImport module orchestrates the import pipeline but delegates
 * dividend processing to TaxCalc via this interface.
 */
interface DividendProcessorPort
{
    /**
     * @param list<NormalizedTransaction> $transactions all imported transactions (any type)
     * @return list<\App\TaxCalc\Domain\ValueObject\DividendTaxResult>
     */
    public function process(
        array $transactions,
        UserId $userId,
        TaxYear $taxYear,
    ): array;
}
