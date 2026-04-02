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
 * Golden Dataset #003 -- Loss scenario (sell below cost).
 *
 * Scenario:
 *   Buy 100 AAPL @ $200, commission $5, NBP 4.00
 *   Sell 100 AAPL @ $150, commission $5, NBP 4.10
 *
 * Hand-calculated values:
 *   costBasis = 100 * 200 * 4.00 = 80000.00 PLN
 *   proceeds  = 100 * 150 * 4.10 = 61500.00 PLN
 *   buyComm   = 5 * 4.00 = 20.00 PLN
 *   sellComm  = 5 * 4.10 = 20.50 PLN
 *   gainLoss  = 61500 - 80000 - 20 - 20.50 = -18540.50 PLN (loss)
 *
 * Tax consequences:
 *   equityIncome = 0 (loss scenario)
 *   equityLoss   = 18540.50 PLN
 *   taxableIncome = 0
 *   tax = 0
 */
final class GoldenDataset003LossScenarioTest extends TestCase
{
    public function testLossScenarioProducesZeroTax(): void
    {
        // ====================================================================
        // STEP 1: TaxPositionLedger -- register buy and sell
        // ====================================================================

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        $buyRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
        $sellRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.10'),
            new \DateTimeImmutable('2025-09-19'),
            '183/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('100'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('5.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
            $converter,
        );

        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('100'),
            Money::of('150.00', CurrencyCode::USD),
            Money::of('5.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
            $converter,
        );

        self::assertCount(1, $closedPositions);
        $closed = $closedPositions[0];

        // --- ClosedPosition verification (hand-calculated) ---

        // costBasis = 100 * 200 * 4.00 = 80000.00
        self::assertTrue(
            $closed->costBasisPLN->isEqualTo('80000.00'),
            "costBasis: 100 * 200 * 4.00 = 80000.00, got: {$closed->costBasisPLN}",
        );

        // proceeds = 100 * 150 * 4.10 = 61500.00
        self::assertTrue(
            $closed->proceedsPLN->isEqualTo('61500.00'),
            "proceeds: 100 * 150 * 4.10 = 61500.00, got: {$closed->proceedsPLN}",
        );

        // buyCommission = 5 * 4.00 = 20.00
        self::assertTrue(
            $closed->buyCommissionPLN->isEqualTo('20.00'),
            "buyCommission: 5 * 4.00 = 20.00, got: {$closed->buyCommissionPLN}",
        );

        // sellCommission = 5 * 4.10 = 20.50
        self::assertTrue(
            $closed->sellCommissionPLN->isEqualTo('20.50'),
            "sellCommission: 5 * 4.10 = 20.50, got: {$closed->sellCommissionPLN}",
        );

        // gainLoss = 61500.00 - 80000.00 - 20.00 - 20.50 = -18540.50
        self::assertTrue(
            $closed->gainLossPLN->isEqualTo('-18540.50'),
            "gainLoss: 61500 - 80000 - 20 - 20.50 = -18540.50, got: {$closed->gainLossPLN}",
        );

        // Confirm it is indeed a loss
        self::assertTrue($closed->gainLossPLN->isNegative(), 'gainLoss should be negative (loss)');

        // ====================================================================
        // STEP 2: AnnualTaxCalculation -- finalize
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = -18540.50 (loss)
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('-18540.50'),
            "equityGainLoss should be -18540.50, got: {$calc->equityGainLoss()}",
        );

        // taxableIncome = max(0, gainLoss) = 0 (loss cannot generate taxable income)
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('0'),
            "equityTaxableIncome should be 0 (loss), got: {$calc->equityTaxableIncome()}",
        );

        // tax = 0
        self::assertTrue(
            $calc->equityTax()->isEqualTo('0'),
            "equityTax should be 0 (loss), got: {$calc->equityTax()}",
        );

        // totalTaxDue = 0
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('0'),
            "totalTaxDue should be 0, got: {$calc->totalTaxDue()}",
        );

        // ====================================================================
        // STEP 3: PIT38XMLGenerator -- verify XML
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
        self::assertSame('61500.00', $equityProceeds, 'equityProceeds should be 61500.00');
        self::assertSame('0', $equityIncome, 'equityIncome should be 0 (loss)');
        self::assertSame('18540.50', $equityLoss, 'equityLoss should be 18540.50');

        $pit38Data = new PIT38Data(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Anna',
            lastName: 'Nowak',
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
        $xpath->registerNamespace('pit', 'http://crd.gov.pl/wzor/2024/12/05/13430/');

        // P_22 = proceeds = 61500.00
        $p22 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_22)');
        self::assertSame('61500.00', $p22, 'P_22 (proceeds): 100 * 150 * 4.10 = 61500.00');

        // P_23 = costs = costBasis + commissions = 80000.00 + 40.50 = 80040.50
        $p23 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_23)');
        self::assertSame('80040.50', $p23, 'P_23 (costs): 80000.00 + 20.00 + 20.50 = 80040.50');

        // P_24 = income = 0 (loss scenario)
        $p24 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_24)');
        self::assertSame('0', $p24, 'P_24 (income): 0 because of loss');

        // P_25 = loss = 18540.50
        $p25 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_25)');
        self::assertSame('18540.50', $p25, 'P_25 (loss): |61500 - 80040.50| = 18540.50');

        // P_27 = tax = 0
        $p27 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_27)');
        self::assertSame('0', $p27, 'P_27 (tax): 0 because of loss');

        // P_51 = total tax = 0
        $p51 = $xpath->evaluate('string(//pit:PozycjeSzczegolowe/pit:P_51)');
        self::assertTrue(
            BigDecimal::of($p51)->isEqualTo('0'),
            'P_51 (totalTax) should equal 0',
        );
    }
}
