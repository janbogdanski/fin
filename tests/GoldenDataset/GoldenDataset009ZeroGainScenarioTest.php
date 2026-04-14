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
 * Golden Dataset #009 — Zero-gain scenario (buy price = sell price, zero commission).
 *
 * Tax rule #11: When proceeds exactly equal cost basis and there are no commissions,
 * gainLoss = 0 and therefore tax = 0.
 *
 * Scenario:
 *   Buy 10 AAPL @ $150, commission $0, NBP 4.00
 *   Sell 10 AAPL @ $150, commission $0, NBP 4.00
 *   (same price, same NBP rate — zero gain/loss)
 *
 * Hand-calculated values:
 *   costBasis = 10 * 150 * 4.00 = 6000.00 PLN
 *   proceeds  = 10 * 150 * 4.00 = 6000.00 PLN
 *   buyComm   = 0 * 4.00 = 0.00 PLN
 *   sellComm  = 0 * 4.00 = 0.00 PLN
 *   gainLoss  = 6000.00 - 6000.00 - 0 - 0 = 0.00 PLN
 *
 * Tax consequences:
 *   equityGainLoss   = 0.00
 *   taxableIncome    = 0
 *   equityTaxDue     = 0
 *   totalTaxDue      = 0
 *
 * @see art. 30b ust. 2 pkt 1 ustawy o PIT — podstawa opodatkowania = dochód, nie sama sprzedaż
 */
final class GoldenDataset009ZeroGainScenarioTest extends TestCase
{
    public function testZeroGainProducesZeroTax(): void
    {
        // ====================================================================
        // STEP 1: TaxPositionLedger — register buy and sell at identical price
        // ====================================================================

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        // Same NBP rate for both transactions (ensures symmetry)
        $rate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('10'),
            Money::of('150.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD), // zero commission
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-15'),
            BigDecimal::of('10'),
            Money::of('150.00', CurrencyCode::USD), // same price as buy
            Money::of('0.00', CurrencyCode::USD),   // zero commission
            BrokerId::of('ibkr'),
            $rate, // same NBP rate
            $converter,
        );

        self::assertCount(1, $closedPositions);
        $closed = $closedPositions[0];

        // --- ClosedPosition verification ---

        // costBasis = 10 * 150 * 4.00 = 6000.00
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('6000.00'),
            "costBasis: 10 * 150 * 4.00 = 6000.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = 10 * 150 * 4.00 = 6000.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('6000.00'),
            "proceeds: 10 * 150 * 4.00 = 6000.00, got: {$closed->proceedsPLN}",
        );

        // buyCommission = 0 * 4.00 = 0.00
        self::assertTrue(
            $closed->buyCommissionPLN->isEqualTo('0.00'),
            "buyCommission: 0 * 4.00 = 0.00, got: {$closed->buyCommissionPLN}",
        );

        // sellCommission = 0 * 4.00 = 0.00
        self::assertTrue(
            $closed->sellCommissionPLN->isEqualTo('0.00'),
            "sellCommission: 0 * 4.00 = 0.00, got: {$closed->sellCommissionPLN}",
        );

        // gainLoss = 6000 - 6000 - 0 - 0 = 0.00
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('0'),
            "gainLoss: 6000 - 6000 - 0 - 0 = 0, got: {$closed->gainLossPLN}",
        );

        // Confirm it is neither positive nor negative
        self::assertFalse($closed->gainLossPLN->isPositive(), 'gainLoss must not be positive');
        self::assertFalse($closed->gainLossPLN->isNegative(), 'gainLoss must not be negative');

        // ====================================================================
        // STEP 2: AnnualTaxCalculation — finalize
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = 0.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('0'),
            "equityGainLoss should be 0, got: {$calc->equityGainLoss()}",
        );

        // taxableIncome = max(0, 0) = 0
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('0'),
            "equityTaxableIncome should be 0, got: {$calc->equityTaxableIncome()}",
        );

        // equityTax = 0 * 0.19 = 0
        self::assertTrue(
            $calc->equityTax()->isEqualTo('0'),
            "equityTax should be 0, got: {$calc->equityTax()}",
        );

        // totalTaxDue = 0
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('0'),
            "totalTaxDue should be 0, got: {$calc->totalTaxDue()}",
        );

        // ====================================================================
        // STEP 3: PIT38XMLGenerator — verify XML
        // ====================================================================

        $equityProceeds = $calc->equityProceeds()->toScale(2)->__toString();
        $equityCostBasis = $calc->equityCostBasis()->__toString();
        $equityCommissions = $calc->equityCommissions()->__toString();
        $equityGainLoss = $calc->equityGainLoss();

        $equityCosts = BigDecimal::of($equityCostBasis)
            ->plus(BigDecimal::of($equityCommissions))
            ->toScale(2)->__toString();

        $equityIncome = $equityGainLoss->isPositive() ? $equityGainLoss->toScale(2)->__toString() : '0';
        $equityLoss = $equityGainLoss->isNegative() ? $equityGainLoss->abs()->toScale(2)->__toString() : '0';

        // Verify intermediate values
        self::assertSame('6000.00', $equityProceeds, 'equityProceeds should be 6000.00');
        self::assertSame('6000.00', $equityCosts, 'equityCosts should be 6000.00 (costBasis + 0 commission)');
        self::assertSame('0', $equityIncome, 'equityIncome should be 0 (zero-gain)');
        self::assertSame('0', $equityLoss, 'equityLoss should be 0 (zero-gain, not a loss)');

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            equityProceeds: $equityProceeds,
            equityCosts: $equityCosts,
            equityIncome: $equityIncome,
            equityLoss: $equityLoss,
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

        $generator = new PIT38XMLGenerator();
        $xml = $generator->generate($pit38Data);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pit', 'http://crd.gov.pl/wzor/2025/10/09/13914/');

        // P_20 = proceeds = 6000.00
        $p20 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_20)');
        self::assertSame('6000.00', $p20, 'P_20 (proceeds): 10 * 150 * 4.00 = 6000.00');

        // P_21 = costs = 6000.00 (costBasis, zero commissions)
        $p21 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_21)');
        self::assertSame('6000.00', $p21, 'P_21 (costs): 6000.00 + 0 = 6000.00');

        // P_28 = income = 0 (zero-gain; both equityIncome and equityLoss are 0, generator emits P_28=0)
        // Per official XSD: when no loss, P_28 (dochod) is emitted even at zero.
        $p28 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_28)');
        self::assertSame('0', $p28, 'P_28 (income): 0 because proceeds == costs (zero-gain, no loss)');
        $p29Nodes = $dom->getElementsByTagName('P_29');
        self::assertSame(0, $p29Nodes->length, 'P_29 should not be emitted when loss is zero');

        // P_43 nie jest emitowany gdy brak kryptowalut (sekcja kryptowalut jest opcjonalna)
        $p43Nodes = $dom->getElementsByTagName('P_43');
        self::assertSame(0, $p43Nodes->length, 'P_43 should not be emitted when crypto is zero');

        // P_51 = total tax = 0
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertTrue(
            BigDecimal::of($p51)->isEqualTo('0'),
            'P_51 (totalTax) should equal 0',
        );
    }
}
