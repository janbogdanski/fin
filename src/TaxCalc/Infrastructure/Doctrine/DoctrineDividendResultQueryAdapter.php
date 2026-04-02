<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Port\DividendResultQueryPort;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Doctrine implementation of DividendResultQueryPort.
 *
 * TODO: Implement when dividend persistence is added (separate table
 * for computed dividend tax results per user/year/country).
 * For now returns empty — dividends are not yet persisted.
 */
final readonly class DoctrineDividendResultQueryAdapter implements DividendResultQueryPort
{
    public function findByUserAndYear(UserId $userId, TaxYear $taxYear): array
    {
        // Dividend tax results are not yet persisted — will be implemented
        // when dividend import flow (DIVIDEND + WITHHOLDING_TAX transactions)
        // and DividendTaxService integration are added.
        return [];
    }
}
