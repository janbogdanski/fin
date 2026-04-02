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
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #008 -- Art. 63 rounding edge cases.
 *
 * Art. 63 ss 1 Ordynacji podatkowej:
 *   Fractional part < 0.50 -> round DOWN (floor)
 *   Fractional part >= 0.50 -> round UP (ceil)
 *
 * Test A: Tax = X.50 -> rounds UP to X+1
 *   taxBase = 50, tax = 50 * 0.19 = 9.50 -> rounds UP to 10
 *   Engineered: Buy 10 CDR @ 100.00 PLN, Sell 10 CDR @ 105.00 PLN, zero commission
 *   gainLoss = 10 * 105 - 10 * 100 = 1050 - 1000 = 50.00
 *   taxBase = round(50.00) = 50
 *   tax = round(50 * 0.19) = round(9.50) = 10 (HALF_UP: .50 rounds UP)
 *
 * Test B: Tax = X.49 -> rounds DOWN to X
 *   taxBase = 71, tax = 71 * 0.19 = 13.49 -> rounds DOWN to 13
 *   Engineered: Buy 10 CDR @ 100.00 PLN, Sell 10 CDR @ 107.10 PLN, zero commission
 *   gainLoss = 10 * 107.10 - 10 * 100 = 1071 - 1000 = 71.00
 *   taxBase = round(71.00) = 71
 *   tax = round(71 * 0.19) = round(13.49) = 13 (HALF_UP: .49 rounds DOWN)
 *
 * Additional edge: taxBase rounding itself
 * Test C: gainLoss = 50.50 -> taxBase rounds UP to 51
 *   tax = round(51 * 0.19) = round(9.69) = 10 (.69 rounds UP)
 *
 * Test D: gainLoss = 50.49 -> taxBase rounds DOWN to 50
 *   tax = round(50 * 0.19) = round(9.50) = 10 (.50 rounds UP)
 */
final class GoldenDataset008Art63RoundingEdgeCasesTest extends TestCase
{
    /**
     * Test A: tax = 9.50 rounds UP to 10 (boundary: exactly .50).
     */
    public function testTaxRoundsUpAtExactlyPointFive(): void
    {
        // gainLoss = 50.00 PLN -> taxBase = 50 -> tax = 50*0.19 = 9.50 -> 10
        $closedPositions = $this->createPLNPosition(
            buyPrice: '100.00',
            sellPrice: '105.00',
            quantity: '10',
        );

        $closed = $closedPositions[0];

        // costBasis = 10 * 100 = 1000.00
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('1000.00'),
            "costBasis: 10 * 100 = 1000.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = 10 * 105 = 1050.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('1050.00'),
            "proceeds: 10 * 105 = 1050.00, got: {$closed->proceedsPLN}",
        );

        // gainLoss = 1050 - 1000 = 50.00
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('50.00'),
            "gainLoss: 1050 - 1000 = 50.00, got: {$closed->gainLossPLN}",
        );

        $calc = $this->buildAndFinalize($closedPositions);

        // taxBase = round(50.00) = 50
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('50'),
            "taxBase: round(50.00) = 50, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(50 * 0.19) = round(9.50) = 10 (.50 >= .50 -> rounds UP)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('10'),
            "tax: round(9.50) = 10 (art. 63: .50 rounds UP), got: {$calc->equityTax()}",
        );
    }

    /**
     * Test B: tax = 13.49 rounds DOWN to 13 (boundary: .49 just below .50).
     */
    public function testTaxRoundsDownAtPointFortyNine(): void
    {
        // gainLoss = 71.00 PLN -> taxBase = 71 -> tax = 71*0.19 = 13.49 -> 13
        $closedPositions = $this->createPLNPosition(
            buyPrice: '100.00',
            sellPrice: '107.10',
            quantity: '10',
        );

        $closed = $closedPositions[0];

        // costBasis = 10 * 100 = 1000.00
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('1000.00'),
            "costBasis: 10 * 100 = 1000.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = 10 * 107.10 = 1071.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('1071.00'),
            "proceeds: 10 * 107.10 = 1071.00, got: {$closed->proceedsPLN}",
        );

        // gainLoss = 1071 - 1000 = 71.00
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('71.00'),
            "gainLoss: 1071 - 1000 = 71.00, got: {$closed->gainLossPLN}",
        );

        $calc = $this->buildAndFinalize($closedPositions);

        // taxBase = round(71.00) = 71
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('71'),
            "taxBase: round(71.00) = 71, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(71 * 0.19) = round(13.49) = 13 (.49 < .50 -> rounds DOWN)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('13'),
            "tax: round(13.49) = 13 (art. 63: .49 rounds DOWN), got: {$calc->equityTax()}",
        );
    }

    /**
     * Test C: taxBase rounding -- gainLoss 50.50 rounds UP to taxBase 51.
     */
    public function testTaxBaseRoundsUpAtExactlyPointFive(): void
    {
        // gainLoss = 50.50 PLN -> taxBase = round(50.50) = 51 -> tax = round(51*0.19) = round(9.69) = 10
        // Buy 100 @ 10.00, Sell 100 @ 10.505 -> but we need integer cents
        // Better: use commissions to create .50 fractional part
        // Buy 10 @ 100 = 1000, commission = 0.50 PLN (via USD trick: $0.125 @ NBP 4.00 = 0.50)
        // Sell 10 @ 105.10 = 1051 -> gainLoss = 1051 - 1000 - 0.50 = 50.50
        // Actually let's keep it simple with PLN fractions:
        // We can use fractional sell price to get fractional proceeds.

        // Buy 1 share @ 100.00 PLN, Sell 1 share @ 150.50 PLN, zero commission
        // gainLoss = 150.50 - 100.00 = 50.50
        $closedPositions = $this->createPLNPosition(
            buyPrice: '100.00',
            sellPrice: '150.50',
            quantity: '1',
        );

        $closed = $closedPositions[0];

        // gainLoss = 150.50 - 100.00 = 50.50
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('50.50'),
            "gainLoss: 150.50 - 100.00 = 50.50, got: {$closed->gainLossPLN}",
        );

        $calc = $this->buildAndFinalize($closedPositions);

        // taxBase = round(50.50) = 51 (art. 63: .50 rounds UP)
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('51'),
            "taxBase: round(50.50) = 51 (art. 63: .50 rounds UP), got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(51 * 0.19) = round(9.69) = 10 (.69 >= .50 -> rounds UP)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('10'),
            "tax: round(9.69) = 10, got: {$calc->equityTax()}",
        );
    }

    /**
     * Test D: taxBase rounding -- gainLoss 50.49 rounds DOWN to taxBase 50.
     */
    public function testTaxBaseRoundsDownAtPointFortyNine(): void
    {
        // Buy 1 share @ 100.00 PLN, Sell 1 share @ 150.49 PLN, zero commission
        // gainLoss = 150.49 - 100.00 = 50.49
        $closedPositions = $this->createPLNPosition(
            buyPrice: '100.00',
            sellPrice: '150.49',
            quantity: '1',
        );

        $closed = $closedPositions[0];

        // gainLoss = 150.49 - 100.00 = 50.49
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('50.49'),
            "gainLoss: 150.49 - 100.00 = 50.49, got: {$closed->gainLossPLN}",
        );

        $calc = $this->buildAndFinalize($closedPositions);

        // taxBase = round(50.49) = 50 (art. 63: .49 < .50 -> rounds DOWN)
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('50'),
            "taxBase: round(50.49) = 50 (art. 63: .49 rounds DOWN), got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(50 * 0.19) = round(9.50) = 10 (.50 rounds UP)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('10'),
            "tax: round(9.50) = 10, got: {$calc->equityTax()}",
        );
    }

    /**
     * Helper: creates a single PLN-only closed position with zero commissions.
     *
     * @return list<\App\TaxCalc\Domain\Model\ClosedPosition>
     */
    private function createPLNPosition(string $buyPrice, string $sellPrice, string $quantity): array
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // using valid ISIN
            TaxCategory::EQUITY,
        );

        $plnRate = NBPRate::create(
            CurrencyCode::PLN,
            BigDecimal::of('1.00'),
            new \DateTimeImmutable('2025-04-10'),
            '071/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-04-11'),
            BigDecimal::of($quantity),
            Money::of($buyPrice, CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('bos'),
            $plnRate,
            $converter,
        );

        return $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-10-15'),
            BigDecimal::of($quantity),
            Money::of($sellPrice, CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('bos'),
            $plnRate,
            $converter,
        );
    }

    /**
     * @param list<\App\TaxCalc\Domain\Model\ClosedPosition> $closedPositions
     */
    private function buildAndFinalize(array $closedPositions): AnnualTaxCalculation
    {
        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        return $calc;
    }
}
