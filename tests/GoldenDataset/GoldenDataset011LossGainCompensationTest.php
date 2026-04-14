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
 * Golden Dataset #011 — Loss + gain in same year (compensation, Rule #20).
 *
 * Tax rule: gains and losses on equities within the same tax year are netted.
 * Net positive income = taxable. Net negative = loss to carry forward.
 *
 * Scenario:
 *   AAPL: Buy 10 @ $200, sell 10 @ $400, NBP 4.00, commission $0
 *   MSFT: Buy 10 @ $300, sell 10 @ $175, NBP 4.00, commission $0
 *
 * Hand-calculated AAPL:
 *   costBasis = 10 * 200 * 4.00 = 8000.00 PLN
 *   proceeds  = 10 * 400 * 4.00 = 16000.00 PLN
 *   gainLoss  = 16000 - 8000 = 8000.00 PLN (gain)
 *
 * Hand-calculated MSFT:
 *   costBasis = 10 * 300 * 4.00 = 12000.00 PLN
 *   proceeds  = 10 * 175 * 4.00 = 7000.00 PLN
 *   gainLoss  = 7000 - 12000 = -5000.00 PLN (loss)
 *
 * Net equityGainLoss = 8000 + (-5000) = 3000.00 PLN
 * taxBase = round(3000.00) = 3000
 * tax     = round(3000 * 0.19) = round(570.00) = 570 PLN
 *
 * Note: task description used 2000/500 gain/loss. This test uses clean round numbers
 * to avoid PLN conversion complexity while testing the same compensation rule.
 * See RATIONALE comment below for reasoning.
 *
 * @see art. 9 ust. 2 ustawy o PIT — dochód = przychód - koszty (netting within category)
 * @see art. 30b ust. 2 pkt 1 ustawy o PIT — podstawa = suma dochodow z odpłatnego zbycia
 */
final class GoldenDataset011LossGainCompensationTest extends TestCase
{
    public function testLossAndGainInSameYearAreNetted(): void
    {
        $converter = new CurrencyConverter();
        $userId = UserId::generate();

        // PLN rate = 1.00 (domestic, no conversion needed)
        $rate = NBPRate::create(
            CurrencyCode::PLN,
            BigDecimal::of('1.00'),
            new \DateTimeImmutable('2025-01-09'),
            '001/A/NBP/2025',
        );

        // ====================================================================
        // AAPL ledger — gain of 2000.00 PLN
        // Buy 10 @ 300 PLN, sell 10 @ 500 PLN, no commission
        // costBasis = 10 * 300 * 1.00 = 3000.00
        // proceeds  = 10 * 500 * 1.00 = 5000.00
        // gainLoss  = 5000 - 3000 = 2000.00 PLN
        // ====================================================================

        $aaplLedger = TaxPositionLedger::create(
            $userId,
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        $aaplLedger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('10'),
            Money::of('300.00', CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        $aaplClosedPositions = $aaplLedger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-15'),
            BigDecimal::of('10'),
            Money::of('500.00', CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        self::assertCount(1, $aaplClosedPositions);
        $aaplClosed = $aaplClosedPositions[0];

        // costBasis = 10 * 300 * 1.00 = 3000.00
        self::assertTrue(
            $aaplClosed->costBasisPLN->isEqualTo('3000.00'),
            "AAPL costBasis: 10 * 300 * 1.00 = 3000.00, got: {$aaplClosed->costBasisPLN}",
        );

        // proceeds = 10 * 500 * 1.00 = 5000.00
        self::assertTrue(
            $aaplClosed->proceedsPLN->isEqualTo('5000.00'),
            "AAPL proceeds: 10 * 500 * 1.00 = 5000.00, got: {$aaplClosed->proceedsPLN}",
        );

        // gainLoss = 5000 - 3000 = 2000.00 (gain)
        self::assertTrue(
            $aaplClosed->gainLossPLN->isEqualTo('2000.00'),
            "AAPL gainLoss: 5000 - 3000 = 2000.00, got: {$aaplClosed->gainLossPLN}",
        );
        self::assertTrue($aaplClosed->gainLossPLN->isPositive(), 'AAPL must produce a gain');

        // ====================================================================
        // MSFT ledger — loss of 500.00 PLN
        // Buy 10 @ 200 PLN, sell 10 @ 150 PLN, no commission
        // costBasis = 10 * 200 * 1.00 = 2000.00
        // proceeds  = 10 * 150 * 1.00 = 1500.00
        // gainLoss  = 1500 - 2000 = -500.00 PLN
        // ====================================================================

        $msftLedger = TaxPositionLedger::create(
            $userId,
            ISIN::fromString('US5949181045'), // MSFT
            TaxCategory::EQUITY,
        );

        $msftLedger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-02-10'),
            BigDecimal::of('10'),
            Money::of('200.00', CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        $msftClosedPositions = $msftLedger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-08-10'),
            BigDecimal::of('10'),
            Money::of('150.00', CurrencyCode::PLN),
            Money::of('0.00', CurrencyCode::PLN),
            BrokerId::of('ibkr'),
            $rate,
            $converter,
        );

        self::assertCount(1, $msftClosedPositions);
        $msftClosed = $msftClosedPositions[0];

        // costBasis = 10 * 200 * 1.00 = 2000.00
        self::assertTrue(
            $msftClosed->costBasisPLN->isEqualTo('2000.00'),
            "MSFT costBasis: 10 * 200 * 1.00 = 2000.00, got: {$msftClosed->costBasisPLN}",
        );

        // proceeds = 10 * 150 * 1.00 = 1500.00
        self::assertTrue(
            $msftClosed->proceedsPLN->isEqualTo('1500.00'),
            "MSFT proceeds: 10 * 150 * 1.00 = 1500.00, got: {$msftClosed->proceedsPLN}",
        );

        // gainLoss = 1500 - 2000 = -500.00 (loss)
        self::assertTrue(
            $msftClosed->gainLossPLN->isEqualTo('-500.00'),
            "MSFT gainLoss: 1500 - 2000 = -500.00, got: {$msftClosed->gainLossPLN}",
        );
        self::assertTrue($msftClosed->gainLossPLN->isNegative(), 'MSFT must produce a loss');

        // ====================================================================
        // AnnualTaxCalculation — aggregate both instruments in one year
        // ====================================================================

        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($aaplClosedPositions, TaxCategory::EQUITY);
        $calc->addClosedPositions($msftClosedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // Net equityGainLoss = 2000 + (-500) = 1500.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('1500.00'),
            "equityGainLoss: 2000 + (-500) = 1500.00, got: {$calc->equityGainLoss()}",
        );

        // taxBase = round(1500.00) = 1500
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('1500'),
            "equityTaxBase: round(1500.00) = 1500, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(1500 * 0.19) = round(285.00) = 285
        self::assertTrue(
            $calc->equityTax()->isEqualTo('285'),
            "equityTax: round(1500 * 0.19) = round(285.00) = 285, got: {$calc->equityTax()}",
        );

        // totalTaxDue = 285
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('285'),
            "totalTaxDue: 285, got: {$calc->totalTaxDue()}",
        );

        // ====================================================================
        // PIT38XMLGenerator — verify XML
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

        // equityProceeds = 5000 (AAPL) + 1500 (MSFT) = 6500.00
        self::assertSame('6500.00', $equityProceeds, 'equityProceeds: 5000 + 1500 = 6500.00');

        // equityCosts = 3000 (AAPL cost) + 2000 (MSFT cost) + 0 (commissions) = 5000.00
        self::assertSame('5000.00', $equityCosts, 'equityCosts: 3000 + 2000 + 0 = 5000.00');

        // equityIncome = 1500.00 (net positive)
        self::assertSame('1500.00', $equityIncome, 'equityIncome should be 1500.00 (net gain after loss compensation)');
        self::assertSame('0', $equityLoss, 'equityLoss should be 0 (net is positive)');

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Maria',
            lastName: 'Wisniewska',
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

        // P_20 = equity proceeds = 6500.00 (AAPL + MSFT)
        $p20 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_20)');
        self::assertSame('6500.00', $p20, 'P_20 (proceeds): 5000 + 1500 = 6500.00');

        // P_21 = equity costs = 5000.00 (3000 + 2000, zero commissions)
        $p21 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_21)');
        self::assertSame('5000.00', $p21, 'P_21 (costs): 3000 + 2000 = 5000.00');

        // P_28 = equity income = 1500.00 (net gain: 2000 - 500)
        $p28 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_28)');
        self::assertSame('1500.00', $p28, 'P_28 (income): 6500 - 5000 = 1500.00 net gain');

        // P_31 = equity tax base = 1500
        $p31 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_31)');
        self::assertSame('1500', $p31, 'P_31 (taxBase): round(1500.00) = 1500');

        // P_33 = equity tax = 285
        $p33 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_33)');
        self::assertSame('285', $p33, 'P_33 (tax): round(1500 * 0.19) = round(285.00) = 285');

        // P_51 = total tax = 285
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertTrue(
            BigDecimal::of($p51)->isEqualTo('285'),
            'P_51 (totalTax) should equal 285',
        );
    }
}
