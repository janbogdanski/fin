<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;

/**
 * Immutable snapshot of a finalized AnnualTaxCalculation.
 *
 * Returned by AnnualTaxCalculation::finalize() — callers use this DTO
 * instead of querying the aggregate directly.
 *
 * @see AnnualTaxCalculation::finalize()
 */
final readonly class TaxCalculationSnapshot
{
    /**
     * @param array<string, DividendCountrySummary> $dividendsByCountry
     */
    public function __construct(
        public UserId $userId,
        public TaxYear $taxYear,
        // Sekcja C PIT-38 — equity + derivatives
        public BigDecimal $equityProceeds,
        public BigDecimal $equityCostBasis,
        public BigDecimal $equityCommissions,
        public BigDecimal $equityGainLoss,
        public BigDecimal $equityLossDeduction,
        public BigDecimal $equityTaxableIncome,
        public BigDecimal $equityTax,
        // Sekcja D — dywidendy zagraniczne
        public array $dividendsByCountry,
        public BigDecimal $dividendTotalTaxDue,
        // Kryptowaluty
        public BigDecimal $cryptoProceeds,
        public BigDecimal $cryptoCostBasis,
        public BigDecimal $cryptoCommissions,
        public BigDecimal $cryptoGainLoss,
        public BigDecimal $cryptoLossDeduction,
        public BigDecimal $cryptoTaxableIncome,
        public BigDecimal $cryptoTax,
        // Suma
        public BigDecimal $totalTaxDue,
    ) {
    }
}
