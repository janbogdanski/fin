<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
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
 * Golden Dataset #012 — Fractional shares with multi-lot FIFO.
 *
 * Real-world scenario: Revolut users commonly hold fractional shares.
 * A single sell may consume multiple partial buy lots via FIFO.
 *
 * Scenario (USD, NBP 4.00 throughout — constant rate for clarity):
 *   Buy  0.5  AAPL @ $180 on 2025-01-10, commission $0
 *   Buy  1.25 AAPL @ $200 on 2025-03-15, commission $0
 *   Sell 1.0  AAPL @ $220 on 2025-06-20, commission $0
 *
 * FIFO matching (oldest first):
 *   CP1: 0.5 shares from first lot (fully consumed)
 *     costBasis  = 0.5 * 180 * 4.00 = 360.00 PLN
 *     proceeds   = 0.5 * 220 * 4.00 = 440.00 PLN
 *     gainLoss   = 440.00 - 360.00 = 80.00 PLN
 *
 *   CP2: 0.5 shares from second lot (partial consume)
 *     costBasis  = 0.5 * 200 * 4.00 = 400.00 PLN
 *     proceeds   = 0.5 * 220 * 4.00 = 440.00 PLN
 *     gainLoss   = 440.00 - 400.00 = 40.00 PLN
 *
 * Remaining open: 0.75 shares from second lot (1.25 bought - 0.5 consumed)
 *
 * Annual calculation:
 *   equityGainLoss = 80.00 + 40.00 = 120.00 PLN
 *   taxBase        = round(120.00) = 120
 *   equityTax      = round(120 * 0.19) = round(22.80) = 23 PLN
 */
final class GoldenDataset012FractionalSharesMultiLotTest extends TestCase
{
    public function testFractionalSharesSellConsumesMultipleFIFOLots(): void
    {
        $converter = new CurrencyConverter();
        $userId    = UserId::generate();

        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-09'),
            '001/A/NBP/2025',
        );

        $ledger = TaxPositionLedger::create(
            $userId,
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        // --- Buy #1: 0.5 AAPL @ $180 ---
        $buy1Id = TransactionId::generate();
        $ledger->registerBuy(
            $buy1Id,
            new \DateTimeImmutable('2025-01-10'),
            BigDecimal::of('0.5'),
            Money::of('180.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rate,
            $converter,
        );

        // --- Buy #2: 1.25 AAPL @ $200 ---
        $buy2Id = TransactionId::generate();
        $ledger->registerBuy(
            $buy2Id,
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('1.25'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rate,
            $converter,
        );

        // --- Sell: 1.0 AAPL @ $220 (consumes all of lot #1 + 0.5 from lot #2) ---
        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-20'),
            BigDecimal::of('1.0'),
            Money::of('220.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rate,
            $converter,
        );

        // FIFO produces exactly 2 ClosedPositions
        self::assertCount(2, $closedPositions);

        // --- CP1: 0.5 shares from first lot ---
        $cp1 = $closedPositions[0];

        self::assertTrue($cp1->buyTransactionId->equals($buy1Id));
        self::assertTrue($cp1->quantity->isEqualTo('0.5'), "CP1 quantity: got {$cp1->quantity}");

        // costBasis = 0.5 * 180 * 4.00 = 360.00 PLN
        self::assertTrue(
            $cp1->costBasisPLN->isEqualTo('360.00'),
            "CP1 costBasis: 0.5 * 180 * 4.00 = 360.00, got: {$cp1->costBasisPLN}",
        );

        // proceeds = 0.5 * 220 * 4.00 = 440.00 PLN
        self::assertTrue(
            $cp1->proceedsPLN->isEqualTo('440.00'),
            "CP1 proceeds: 0.5 * 220 * 4.00 = 440.00, got: {$cp1->proceedsPLN}",
        );

        // gainLoss = 440.00 - 360.00 = 80.00 PLN
        self::assertTrue(
            $cp1->gainLossPLN->isEqualTo('80.00'),
            "CP1 gainLoss: 440.00 - 360.00 = 80.00, got: {$cp1->gainLossPLN}",
        );

        // --- CP2: 0.5 shares from second lot (partial consume) ---
        $cp2 = $closedPositions[1];

        self::assertTrue($cp2->buyTransactionId->equals($buy2Id));
        self::assertTrue($cp2->quantity->isEqualTo('0.5'), "CP2 quantity: got {$cp2->quantity}");

        // costBasis = 0.5 * 200 * 4.00 = 400.00 PLN
        self::assertTrue(
            $cp2->costBasisPLN->isEqualTo('400.00'),
            "CP2 costBasis: 0.5 * 200 * 4.00 = 400.00, got: {$cp2->costBasisPLN}",
        );

        // proceeds = 0.5 * 220 * 4.00 = 440.00 PLN
        self::assertTrue(
            $cp2->proceedsPLN->isEqualTo('440.00'),
            "CP2 proceeds: 0.5 * 220 * 4.00 = 440.00, got: {$cp2->proceedsPLN}",
        );

        // gainLoss = 440.00 - 400.00 = 40.00 PLN
        self::assertTrue(
            $cp2->gainLossPLN->isEqualTo('40.00'),
            "CP2 gainLoss: 440.00 - 400.00 = 40.00, got: {$cp2->gainLossPLN}",
        );

        // --- Remaining open positions ---
        // 0.75 shares from second lot (1.25 bought - 0.5 consumed)
        $open = $ledger->openPositions();
        self::assertCount(1, $open);
        self::assertTrue(
            $open[0]->remainingQuantity()->isEqualTo('0.75'),
            "Remaining: 1.25 - 0.5 = 0.75, got: {$open[0]->remainingQuantity()}",
        );

        // --- Annual calculation ---
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = 80.00 + 40.00 = 120.00 PLN
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('120.00'),
            "equityGainLoss: 80.00 + 40.00 = 120.00, got: {$calc->equityGainLoss()}",
        );

        // taxBase = round(120.00) = 120
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('120'),
            "equityTaxBase: round(120.00) = 120, got: {$calc->equityTaxableIncome()}",
        );

        // equityTax = round(120 * 0.19) = round(22.80) = 23
        self::assertTrue(
            $calc->equityTax()->isEqualTo('23'),
            "equityTax: round(120 * 0.19) = round(22.80) = 23, got: {$calc->equityTax()}",
        );

        // --- XML verification ---
        $proceedsPLN   = $calc->equityProceeds()->toScale(2)->__toString();   // 880.00
        $costBasisPLN  = $calc->equityCostBasis()->toScale(2)->__toString();  // 760.00
        $commissionPLN = $calc->equityCommissions()->toScale(2)->__toString(); // 0.00

        // equityProceeds = 440.00 (CP1) + 440.00 (CP2) = 880.00
        self::assertSame('880.00', $proceedsPLN, 'equityProceeds: 440 + 440 = 880.00');

        // equityCosts = 360.00 (CP1 cost) + 400.00 (CP2 cost) = 760.00
        $totalCosts = BigDecimal::of($costBasisPLN)
            ->plus(BigDecimal::of($commissionPLN))
            ->toScale(2)->__toString();
        self::assertSame('760.00', $totalCosts, 'equityCosts: 360 + 400 = 760.00');

        $gainLoss     = $calc->equityGainLoss();
        $incomeStr    = $gainLoss->isPositive() ? $gainLoss->toScale(2)->__toString() : '0.00';
        $lossStr      = '0.00';

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Piotr',
            lastName: 'Kowalski',
            equityProceeds: $proceedsPLN,
            equityCosts: $totalCosts,
            equityIncome: $incomeStr,
            equityLoss: $lossStr,
            equityTaxBase: $calc->equityTaxableIncome()->__toString(),
            equityTax: $calc->equityTax()->__toString(),
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: $calc->totalTaxDue()->__toString(),
            isCorrection: false,
        );

        $xml = (new PIT38XMLGenerator())->generate($pit38Data);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pit', 'http://crd.gov.pl/wzor/2025/10/09/13914/');

        // P_20 = equity proceeds = 880.00
        $p20 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_20)');
        self::assertSame('880.00', $p20, 'P_20 (proceeds): 440 + 440 = 880.00');

        // P_21 = equity costs = 760.00
        $p21 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_21)');
        self::assertSame('760.00', $p21, 'P_21 (costs): 360 + 400 = 760.00');

        // P_28 = income = 120.00
        $p28 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_28)');
        self::assertSame('120.00', $p28, 'P_28 (income): 880 - 760 = 120.00');

        // P_31 = tax base = 120
        $p31 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_31)');
        self::assertSame('120', $p31, 'P_31 (taxBase): round(120.00) = 120');

        // P_33 = tax = 23
        $p33 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_33)');
        self::assertSame('23', $p33, 'P_33 (tax): round(120 * 0.19) = 23');

        // P_51 = total tax = 23
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertSame('23', $p51, 'P_51 (totalTax): 23');
    }
}
