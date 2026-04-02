<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Model;

use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Policy\TaxRoundingPolicy;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Aggregate — roczne podsumowanie podatkowe per user.
 *
 * Zbiera wyniki z ClosedPositions (equity/derivatives + crypto)
 * oraz dywidend zagranicznych. Oblicza podatek 19%.
 *
 * Struktura odpowiada sekcjom PIT-38:
 * - Sekcja C: zyski kapitalowe (equity + derivatives) — wspolny koszyk
 * - Sekcja D: dywidendy zagraniczne — per country
 * - Osobny koszyk: kryptowaluty (art. 30b ust. 5a-5g)
 *
 * @see art. 30b ustawy o PIT — podatek od zyskow kapitalowych (19%)
 * @see art. 63 ss 1 Ordynacji podatkowej — zaokraglanie
 */
final class AnnualTaxCalculation
{
    private const string TAX_RATE = '0.19';

    private UserId $userId;

    private TaxYear $taxYear;

    // Sekcja C PIT-38 — equity + derivatives (wspolny koszyk)
    private BigDecimal $equityProceeds;

    private BigDecimal $equityCostBasis;

    private BigDecimal $equityCommissions;

    private BigDecimal $equityGainLoss;

    private BigDecimal $equityLossDeduction;

    private BigDecimal $equityTaxableIncome;

    private BigDecimal $equityTax;

    // Sekcja D PIT-38 — dywidendy zagraniczne
    /**
     * @var array<string, DividendCountrySummary> key = country code
     */
    private array $dividendsByCountry;

    private BigDecimal $dividendTotalTaxDue;

    // Osobny koszyk — kryptowaluty (art. 30b ust. 5a-5g)
    private BigDecimal $cryptoProceeds;

    private BigDecimal $cryptoCostBasis;

    private BigDecimal $cryptoCommissions;

    private BigDecimal $cryptoGainLoss;

    private BigDecimal $cryptoLossDeduction;

    private BigDecimal $cryptoTaxableIncome;

    private BigDecimal $cryptoTax;

    // Suma
    private BigDecimal $totalTaxDue;

    private bool $finalized = false;

    private function __construct()
    {
    }

    public static function create(UserId $userId, TaxYear $taxYear): self
    {
        $calc = new self();
        $calc->userId = $userId;
        $calc->taxYear = $taxYear;

        $zero = BigDecimal::zero();

        $calc->equityProceeds = $zero;
        $calc->equityCostBasis = $zero;
        $calc->equityCommissions = $zero;
        $calc->equityGainLoss = $zero;
        $calc->equityLossDeduction = $zero;
        $calc->equityTaxableIncome = $zero;
        $calc->equityTax = $zero;

        $calc->dividendsByCountry = [];
        $calc->dividendTotalTaxDue = $zero;

        $calc->cryptoProceeds = $zero;
        $calc->cryptoCostBasis = $zero;
        $calc->cryptoCommissions = $zero;
        $calc->cryptoGainLoss = $zero;
        $calc->cryptoLossDeduction = $zero;
        $calc->cryptoTaxableIncome = $zero;
        $calc->cryptoTax = $zero;

        $calc->totalTaxDue = $zero;

        return $calc;
    }

    /**
     * Agreguje zamkniete pozycje per TaxCategory (equity/derivative vs crypto).
     * EQUITY i DERIVATIVE trafiaja do wspolnego koszyka (sekcja C PIT-38).
     *
     * @param list<ClosedPosition> $closedPositions
     * @param TaxCategory $taxCategory kategoria podatkowa pozycji
     */
    public function addClosedPositions(array $closedPositions, TaxCategory $taxCategory): void
    {
        $this->guardNotFinalized();

        foreach ($closedPositions as $position) {
            $proceeds = $position->proceedsPLN;
            $costBasis = $position->costBasisPLN;
            $commissions = $position->buyCommissionPLN->plus($position->sellCommissionPLN);
            $gainLoss = $position->gainLossPLN;

            if ($taxCategory === TaxCategory::CRYPTO) {
                $this->cryptoProceeds = $this->cryptoProceeds->plus($proceeds);
                $this->cryptoCostBasis = $this->cryptoCostBasis->plus($costBasis);
                $this->cryptoCommissions = $this->cryptoCommissions->plus($commissions);
                $this->cryptoGainLoss = $this->cryptoGainLoss->plus($gainLoss);
            } else {
                // EQUITY + DERIVATIVE — wspolny koszyk sekcja C
                $this->equityProceeds = $this->equityProceeds->plus($proceeds);
                $this->equityCostBasis = $this->equityCostBasis->plus($costBasis);
                $this->equityCommissions = $this->equityCommissions->plus($commissions);
                $this->equityGainLoss = $this->equityGainLoss->plus($gainLoss);
            }
        }
    }

    /**
     * Agreguje wynik podatkowy dywidendy zagranicznej per country.
     */
    public function addDividendResult(DividendTaxResult $result): void
    {
        $this->guardNotFinalized();

        $countryCode = $result->sourceCountry->value;
        $grossPLN = $result->grossDividendPLN->amount();
        $whtPLN = $result->whtPaidPLN->amount();
        $taxDue = $result->polishTaxDue->amount();

        $summary = new DividendCountrySummary(
            country: $result->sourceCountry,
            grossDividendPLN: $grossPLN,
            whtPaidPLN: $whtPLN,
            polishTaxDue: $taxDue,
        );

        if (isset($this->dividendsByCountry[$countryCode])) {
            $this->dividendsByCountry[$countryCode] = $this->dividendsByCountry[$countryCode]->add($summary);
        } else {
            $this->dividendsByCountry[$countryCode] = $summary;
        }

        $this->recalculateDividendTotal();
    }

    /**
     * Stosuje odpis strat z lat poprzednich.
     *
     * @param list<LossDeductionRange> $ranges dostepne zakresy odliczen
     * @param list<BigDecimal> $chosenAmounts kwoty wybrane do odliczenia (1:1 z ranges)
     */
    public function applyPriorYearLosses(array $ranges, array $chosenAmounts): void
    {
        $this->guardNotFinalized();

        if (count($ranges) !== count($chosenAmounts)) {
            throw new \InvalidArgumentException(
                'Number of ranges and chosen amounts must match.',
            );
        }

        $equityDeduction = BigDecimal::zero();
        $cryptoDeduction = BigDecimal::zero();

        foreach ($ranges as $index => $range) {
            $chosen = $chosenAmounts[$index];

            if ($chosen->isNegative()) {
                throw new \InvalidArgumentException(
                    "Chosen deduction amount cannot be negative: {$chosen}",
                );
            }

            if ($chosen->isGreaterThan($range->maxDeductionThisYear)) {
                throw new \InvalidArgumentException(
                    "Chosen deduction {$chosen} exceeds max allowed {$range->maxDeductionThisYear} for loss year {$range->lossYear->value}.",
                );
            }

            if ($range->taxCategory === TaxCategory::CRYPTO) {
                $cryptoDeduction = $cryptoDeduction->plus($chosen);
            } else {
                $equityDeduction = $equityDeduction->plus($chosen);
            }
        }

        $this->equityLossDeduction = $equityDeduction;
        $this->cryptoLossDeduction = $cryptoDeduction;
    }

    /**
     * Finalizuje obliczenie — taxableIncome i tax (19%).
     * Zaokragla per art. 63 ss 1 Ordynacji podatkowej.
     *
     * Podatek nie moze byc ujemny — strata nie generuje zwrotu.
     *
     * Returns an immutable TaxCalculationSnapshot DTO — callers should
     * use the snapshot instead of querying the aggregate directly.
     */
    public function finalize(): TaxCalculationSnapshot
    {
        $this->guardNotFinalized();

        // Equity: taxableIncome = gainLoss - lossDeduction (min 0)
        $equityIncomeRaw = $this->equityGainLoss->minus($this->equityLossDeduction);
        $this->equityTaxableIncome = TaxRoundingPolicy::roundTaxBase(
            BigDecimal::max($equityIncomeRaw, BigDecimal::zero()),
        );
        $this->equityTax = TaxRoundingPolicy::roundTax(
            $this->equityTaxableIncome->multipliedBy(self::TAX_RATE),
        );

        // Crypto: osobny koszyk
        $cryptoIncomeRaw = $this->cryptoGainLoss->minus($this->cryptoLossDeduction);
        $this->cryptoTaxableIncome = TaxRoundingPolicy::roundTaxBase(
            BigDecimal::max($cryptoIncomeRaw, BigDecimal::zero()),
        );
        $this->cryptoTax = TaxRoundingPolicy::roundTax(
            $this->cryptoTaxableIncome->multipliedBy(self::TAX_RATE),
        );

        // Dividend total — already calculated per-country, sum rounded
        $this->recalculateDividendTotal();
        $this->dividendTotalTaxDue = $this->dividendTotalTaxDue
            ->toScale(2, RoundingMode::HALF_UP);

        // Total
        $this->totalTaxDue = $this->equityTax
            ->plus($this->dividendTotalTaxDue)
            ->plus($this->cryptoTax);

        $this->finalized = true;

        return $this->toSnapshot();
    }

    public function toSnapshot(): TaxCalculationSnapshot
    {
        return new TaxCalculationSnapshot(
            userId: $this->userId,
            taxYear: $this->taxYear,
            equityProceeds: $this->equityProceeds,
            equityCostBasis: $this->equityCostBasis,
            equityCommissions: $this->equityCommissions,
            equityGainLoss: $this->equityGainLoss,
            equityLossDeduction: $this->equityLossDeduction,
            equityTaxableIncome: $this->equityTaxableIncome,
            equityTax: $this->equityTax,
            dividendsByCountry: $this->dividendsByCountry,
            dividendTotalTaxDue: $this->dividendTotalTaxDue,
            cryptoProceeds: $this->cryptoProceeds,
            cryptoCostBasis: $this->cryptoCostBasis,
            cryptoCommissions: $this->cryptoCommissions,
            cryptoGainLoss: $this->cryptoGainLoss,
            cryptoLossDeduction: $this->cryptoLossDeduction,
            cryptoTaxableIncome: $this->cryptoTaxableIncome,
            cryptoTax: $this->cryptoTax,
            totalTaxDue: $this->totalTaxDue,
        );
    }

    // --- Getters ---

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function taxYear(): TaxYear
    {
        return $this->taxYear;
    }

    public function equityProceeds(): BigDecimal
    {
        return $this->equityProceeds;
    }

    public function equityCostBasis(): BigDecimal
    {
        return $this->equityCostBasis;
    }

    public function equityCommissions(): BigDecimal
    {
        return $this->equityCommissions;
    }

    public function equityGainLoss(): BigDecimal
    {
        return $this->equityGainLoss;
    }

    public function equityLossDeduction(): BigDecimal
    {
        return $this->equityLossDeduction;
    }

    public function equityTaxableIncome(): BigDecimal
    {
        return $this->equityTaxableIncome;
    }

    public function equityTax(): BigDecimal
    {
        return $this->equityTax;
    }

    /**
     * @return array<string, DividendCountrySummary>
     */
    public function dividendsByCountry(): array
    {
        return $this->dividendsByCountry;
    }

    public function dividendTotalTaxDue(): BigDecimal
    {
        return $this->dividendTotalTaxDue;
    }

    public function cryptoProceeds(): BigDecimal
    {
        return $this->cryptoProceeds;
    }

    public function cryptoCostBasis(): BigDecimal
    {
        return $this->cryptoCostBasis;
    }

    public function cryptoCommissions(): BigDecimal
    {
        return $this->cryptoCommissions;
    }

    public function cryptoGainLoss(): BigDecimal
    {
        return $this->cryptoGainLoss;
    }

    public function cryptoLossDeduction(): BigDecimal
    {
        return $this->cryptoLossDeduction;
    }

    public function cryptoTaxableIncome(): BigDecimal
    {
        return $this->cryptoTaxableIncome;
    }

    public function cryptoTax(): BigDecimal
    {
        return $this->cryptoTax;
    }

    public function totalTaxDue(): BigDecimal
    {
        return $this->totalTaxDue;
    }

    public function isFinalized(): bool
    {
        return $this->finalized;
    }

    // --- Internal ---

    private function recalculateDividendTotal(): void
    {
        $total = BigDecimal::zero();
        foreach ($this->dividendsByCountry as $summary) {
            $total = $total->plus($summary->polishTaxDue);
        }
        $this->dividendTotalTaxDue = $total;
    }

    private function guardNotFinalized(): void
    {
        if ($this->finalized) {
            throw new \LogicException(
                'AnnualTaxCalculation is already finalized. Cannot modify.',
            );
        }
    }
}
