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
use App\Tests\GoldenDataset\Concern\AssertsPIT38XmlValid;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #005 -- PLN-only transactions (no currency conversion).
 *
 * Scenario:
 *   Buy 100 CDR @ 300.00 PLN, commission 10.00 PLN, NBP rate 1.0 (PLN -> PLN)
 *   Sell 100 CDR @ 350.00 PLN, commission 10.00 PLN, NBP rate 1.0
 *
 * Hand-calculated values:
 *   costBasis    = 100 * 300 * 1.0 = 30000.00 PLN
 *   proceeds     = 100 * 350 * 1.0 = 35000.00 PLN
 *   buyComm      = 10 * 1.0 = 10.00 PLN
 *   sellComm     = 10 * 1.0 = 10.00 PLN
 *   gainLoss     = 35000 - 30000 - 10 - 10 = 4980.00 PLN
 *   taxBase      = round(4980.00) = 4980 (art. 63)
 *   tax          = round(4980 * 0.19) = round(946.20) = 946 (art. 63)
 *
 * Note: CurrencyConverter returns Money as-is when currency is PLN.
 * NBP rate with PLN/1.0 is a modeling convenience for PLN brokers.
 */
final class GoldenDataset005PLNOnlyTransactionsTest extends TestCase
{
    use AssertsPIT38XmlValid;

    public function testPLNOnlyTransactionsNoConversion(): void
    {
        // ====================================================================
        // STEP 1: TaxPositionLedger -- register buy and sell in PLN
        // ====================================================================

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // using valid ISIN for PLN test
            TaxCategory::EQUITY,
        );

        // PLN "rate" -- CurrencyConverter passes PLN through without conversion
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
            BigDecimal::of('100'),
            Money::of('300.00', CurrencyCode::PLN),
            Money::of('10.00', CurrencyCode::PLN),
            BrokerId::of('bos'),
            $plnRate,
            $converter,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-10-15'),
            BigDecimal::of('100'),
            Money::of('350.00', CurrencyCode::PLN),
            Money::of('10.00', CurrencyCode::PLN),
            BrokerId::of('bos'),
            $plnRate,
            $converter,
        );

        self::assertCount(1, $closedPositions);
        $closed = $closedPositions[0];

        // --- ClosedPosition verification ---

        // costBasis = 100 * 300 = 30000.00 (PLN pass-through, no FX)
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('30000.00'),
            "costBasis: 100 * 300 = 30000.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = 100 * 350 = 35000.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('35000.00'),
            "proceeds: 100 * 350 = 35000.00, got: {$closed->proceedsPLN}",
        );

        // buyCommission = 10.00 PLN (pass-through)
        self::assertTrue(
            $closed->buyCommissionPLN->isEqualTo('10.00'),
            "buyCommission: 10.00, got: {$closed->buyCommissionPLN}",
        );

        // sellCommission = 10.00 PLN
        self::assertTrue(
            $closed->sellCommissionPLN->isEqualTo('10.00'),
            "sellCommission: 10.00, got: {$closed->sellCommissionPLN}",
        );

        // gainLoss = 35000 - 30000 - 10 - 10 = 4980.00
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('4980.00'),
            "gainLoss: 35000 - 30000 - 10 - 10 = 4980.00, got: {$closed->gainLossPLN}",
        );

        // ====================================================================
        // STEP 2: AnnualTaxCalculation -- finalize
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = 4980.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('4980.00'),
            "equityGainLoss should be 4980.00, got: {$calc->equityGainLoss()}",
        );

        // taxBase = round(4980.00) = 4980 (art. 63: .00 < 0.50 -> floor)
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('4980'),
            "equityTaxBase: round(4980.00) = 4980, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(4980 * 0.19) = round(946.20) = 946 (.20 < .50 -> floor)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('946'),
            "equityTax: round(4980 * 0.19) = round(946.20) = 946, got: {$calc->equityTax()}",
        );

        // totalTaxDue = 946
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('946'),
            "totalTaxDue: 946, got: {$calc->totalTaxDue()}",
        );

        // ====================================================================
        // STEP 3: PIT38XMLGenerator
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

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Marek',
            lastName: 'Wisniewski',
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

        $this->assertPIT38XmlValidatesAgainstSchema($xml);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('pit', 'http://crd.gov.pl/wzor/2025/10/09/13914/');

        // P_20 = proceeds = 35000.00
        $p20 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_20)');
        self::assertSame('35000.00', $p20, 'P_20 (proceeds): 100 * 350 = 35000.00');

        // P_21 = costs = 30000.00 + 20.00 = 30020.00
        $p21 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_21)');
        self::assertSame('30020.00', $p21, 'P_21 (costs): 30000 + 10 + 10 = 30020.00');

        // P_28 = income = 4980.00
        $p28 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_28)');
        self::assertSame('4980.00', $p28, 'P_28 (income): 35000 - 30020 = 4980.00');

        // P_31 = tax base = 4980
        $p31 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_31)');
        self::assertSame('4980', $p31, 'P_31 (taxBase): round(4980.00) = 4980');

        // P_33 = tax = 946
        $p33 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_33)');
        self::assertSame('946', $p33, 'P_33 (tax): round(4980 * 0.19) = round(946.20) = 946');

        // P_51 = total tax = 946
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertTrue(
            BigDecimal::of($p51)->isEqualTo('946'),
            'P_51 (totalTax) should equal 946',
        );
    }
}
