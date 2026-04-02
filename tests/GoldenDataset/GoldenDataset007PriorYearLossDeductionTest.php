<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\LossDeductionRange;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #007 -- Prior year loss deduction (art. 9 ust. 3 PIT).
 *
 * Scenario:
 *   Equity gain this year: 20000.00 PLN (from a single position)
 *   Prior year loss (2023): originalAmount 15000, remainingAmount 10000, maxDeduction 5000
 *   Chosen deduction: 5000 PLN (full max allowed for this year)
 *
 * Hand-calculated values:
 *   equityGainLoss = 20000.00
 *   lossDeduction  = 5000
 *   taxableIncome  = max(0, 20000 - 5000) = 15000
 *   taxBase        = round(15000) = 15000 (art. 63)
 *   tax            = round(15000 * 0.19) = round(2850.00) = 2850 (art. 63)
 *
 * @see art. 9 ust. 3 ustawy o PIT -- odliczenie strat z lat poprzednich
 */
final class GoldenDataset007PriorYearLossDeductionTest extends TestCase
{
    public function testPriorYearLossDeduction(): void
    {
        // ====================================================================
        // STEP 1: Create a position with 20000 PLN gain
        // ====================================================================

        // We need proceeds - costBasis - commissions = 20000.00
        // Buy 100 shares @ $200, NBP 4.00 -> costBasis = 80000.00
        // Sell 100 shares @ $250, NBP 4.00 -> proceeds = 100000.00
        // Commissions = 0
        // gainLoss = 100000 - 80000 = 20000.00

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-05-09'),
            '090/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-05-10'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-11-15'),
            BigDecimal::of('100'),
            Money::of('250.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        self::assertCount(1, $closedPositions);

        // Verify gain = 20000
        $closed = $closedPositions[0];
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('20000.00'),
            "gainLoss: 100000 - 80000 = 20000.00, got: {$closed->gainLossPLN}",
        );

        // ====================================================================
        // STEP 2: AnnualTaxCalculation with prior year loss deduction
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);

        // Prior year loss from 2023: original 15000, remaining 10000, max deduction 5000
        // Art. 9 ust. 3: max 50% of loss per year, and loss expires after 5 years
        $lossRange = new LossDeductionRange(
            taxCategory: TaxCategory::EQUITY,
            lossYear: TaxYear::of(2023),
            originalAmount: BigDecimal::of('15000'),
            remainingAmount: BigDecimal::of('10000'),
            maxDeductionThisYear: BigDecimal::of('5000'),
            expiresInYear: TaxYear::of(2028),
            yearsRemaining: 3,
        );

        $calc->applyPriorYearLosses(
            ranges: [$lossRange],
            chosenAmounts: [BigDecimal::of('5000')],
        );

        $calc->finalize();

        // equityGainLoss = 20000.00 (before deduction)
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('20000.00'),
            "equityGainLoss: 20000.00, got: {$calc->equityGainLoss()}",
        );

        // lossDeduction = 5000
        self::assertTrue(
            $calc->equityLossDeduction()->isEqualTo('5000'),
            "equityLossDeduction: 5000, got: {$calc->equityLossDeduction()}",
        );

        // taxableIncome = round(20000 - 5000) = round(15000.00) = 15000
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('15000'),
            "equityTaxableIncome: 20000 - 5000 = 15000, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(15000 * 0.19) = round(2850.00) = 2850
        self::assertTrue(
            $calc->equityTax()->isEqualTo('2850'),
            "equityTax: round(15000 * 0.19) = 2850, got: {$calc->equityTax()}",
        );

        // totalTaxDue = 2850
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('2850'),
            "totalTaxDue: 2850, got: {$calc->totalTaxDue()}",
        );
    }
}
