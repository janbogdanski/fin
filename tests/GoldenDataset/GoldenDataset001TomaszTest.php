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
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #001 — "Tomasz" scenario.
 *
 * Full end-to-end flow: TaxPositionLedger -> AnnualTaxCalculation -> PIT38XMLGenerator.
 *
 * Scenario:
 *   Buy 100 AAPL @ $170, commission $1, NBP 4.05 (kurs z 14.03.2025)
 *   Sell 100 AAPL @ $200, commission $1, NBP 3.95 (kurs z 19.09.2025)
 *
 * Hand-calculated values documented in assertions below.
 */
final class GoldenDataset001TomaszTest extends TestCase
{
    public function testFullFlowBuySellTaxXml(): void
    {
        // ====================================================================
        // STEP 1-3: TaxPositionLedger — register buy and sell, get ClosedPosition
        // ====================================================================

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        // NBP rate from last business day BEFORE transaction date (art. 11a ust. 1 PIT)
        $buyRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
        $sellRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.95'),
            new \DateTimeImmutable('2025-09-19'),
            '183/A/NBP/2025',
        );

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
        );

        self::assertCount(1, $closedPositions);
        $closed = $closedPositions[0];

        // --- ClosedPosition verification (hand-calculated) ---

        // costBasis = quantity * pricePerUnit * buyNBPRate = 100 * 170 * 4.05 = 68850.00
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('68850.00'),
            "costBasis: 100 * 170 * 4.05 = 68850.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = quantity * pricePerUnit * sellNBPRate = 100 * 200 * 3.95 = 79000.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('79000.00'),
            "proceeds: 100 * 200 * 3.95 = 79000.00, got: {$closed->proceedsPLN}",
        );

        // buyCommission = commission * buyNBPRate = 1 * 4.05 = 4.05
        self::assertTrue(
            $closed->buyCommissionPLN->isEqualTo('4.05'),
            "buyCommission: 1 * 4.05 = 4.05, got: {$closed->buyCommissionPLN}",
        );

        // sellCommission = commission * sellNBPRate = 1 * 3.95 = 3.95
        self::assertTrue(
            $closed->sellCommissionPLN->isEqualTo('3.95'),
            "sellCommission: 1 * 3.95 = 3.95, got: {$closed->sellCommissionPLN}",
        );

        // gainLoss = proceeds - costBasis - buyComm - sellComm
        //          = 79000.00 - 68850.00 - 4.05 - 3.95 = 10142.00
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('10142.00'),
            "gainLoss: 79000 - 68850 - 4.05 - 3.95 = 10142.00, got: {$closed->gainLossPLN}",
        );

        // ====================================================================
        // STEP 5-8: AnnualTaxCalculation — finalize with rounding
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = sum of gainLossPLN = 10142.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('10142.00'),
            "equityGainLoss should be 10142.00, got: {$calc->equityGainLoss()}",
        );

        // equityTaxBase = roundTaxBase(10142.00) = 10142 (art. 63 ss 1 OP: .00 < 0.50 -> floor)
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('10142'),
            "equityTaxBase (art. 63): round(10142.00) = 10142, got: {$calc->equityTaxableIncome()}",
        );

        // equityTax = roundTax(10142 * 0.19) = roundTax(1926.98) = 1927
        // 1926.98 -> .98 >= 0.50, rounds UP to 1927 (art. 63 ss 1 OP)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('1927'),
            "equityTax: round(10142 * 0.19) = round(1926.98) = 1927, got: {$calc->equityTax()}",
        );

        // ====================================================================
        // STEP 9-11: PIT38XMLGenerator — generate and parse XML
        // ====================================================================

        // Build PIT38Data from calculation results
        // P_22 = equityProceeds = 79000.00
        // P_23 = equityCosts = costBasis + commissions = 68850.00 + 4.05 + 3.95 = 68858.00
        // P_24 = equityIncome (when positive) = equityGainLoss = 10142.00
        // P_25 = equityLoss (when negative) = 0
        $equityProceeds = $calc->equityProceeds()->toScale(2)->__toString(); // 79000.00
        $equityCostBasis = $calc->equityCostBasis()->__toString(); // 68850.00
        $equityCommissions = $calc->equityCommissions()->__toString(); // 8.00
        $equityGainLoss = $calc->equityGainLoss(); // 10142.00

        // P_23 = costBasis + commissions = 68850.00 + 8.00 = 68858.00
        $equityCosts = BigDecimal::of($equityCostBasis)
            ->plus(BigDecimal::of($equityCommissions))
            ->toScale(2)->__toString();

        // Income vs loss
        $equityIncome = $equityGainLoss->isPositive() ? $equityGainLoss->toScale(2)->__toString() : '0';
        $equityLoss = $equityGainLoss->isNegative() ? $equityGainLoss->abs()->toScale(2)->__toString() : '0';

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '1234567890',
            firstName: 'Tomasz',
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

        // Parse XML and verify key positions
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pit', 'http://crd.gov.pl/wzor/2024/12/05/13430/');

        // P_22 = equity proceeds = 79000.00
        $p22 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_22)');
        self::assertSame(
            '79000.00',
            $p22,
            'P_22 (proceeds): 100 * 200 * 3.95 = 79000.00',
        );

        // P_23 = equity costs = costBasis + all commissions = 68850.00 + 4.05 + 3.95 = 68858.00
        $p23 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_23)');
        self::assertSame(
            '68858.00',
            $p23,
            'P_23 (costs): 68850.00 + 4.05 + 3.95 = 68858.00',
        );

        // P_24 = equity income = 10142.00
        $p24 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_24)');
        self::assertSame(
            '10142.00',
            $p24,
            'P_24 (income): 79000.00 - 68858.00 = 10142.00',
        );

        // P_26 = equity tax base (art. 63 rounded) = 10142
        $p26 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_26)');
        self::assertSame(
            '10142',
            $p26,
            'P_26 (taxBase): round(10142.00) = 10142 (art. 63)',
        );

        // P_27 = equity tax = 1927
        $p27 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_27)');
        self::assertSame(
            '1927',
            $p27,
            'P_27 (tax): round(10142 * 0.19) = round(1926.98) = 1927 (art. 63)',
        );

        // P_51 = total tax = 1927 (may have trailing ".00" from BigDecimal sum with scale 2)
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertSame(
            $calc->totalTaxDue()->__toString(),
            $p51,
            'P_51 (totalTax): equityTax + dividendTax + cryptoTax = 1927 + 0 + 0',
        );
        // Verify the numeric value regardless of scale representation
        self::assertTrue(
            BigDecimal::of($p51)->isEqualTo('1927'),
            'P_51 numeric value should equal 1927',
        );

        // Verify taxpayer data in XML
        $firstName = $xpath->evaluate('string(//pit:Podmiot1/pit:OsobaFizyczna/pit:ImiePierwsze)');
        self::assertSame('Tomasz', $firstName);

        $lastName = $xpath->evaluate('string(//pit:Podmiot1/pit:OsobaFizyczna/pit:Nazwisko)');
        self::assertSame('Kowalski', $lastName);

        $year = $xpath->evaluate('string(//pit:Naglowek/pit:Rok)');
        self::assertSame('2025', $year);
    }
}
