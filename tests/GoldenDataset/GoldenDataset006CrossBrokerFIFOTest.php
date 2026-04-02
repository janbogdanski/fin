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
 * Golden Dataset #006 -- Cross-broker FIFO (3 brokers, same ISIN).
 *
 * Scenario:
 *   Buy 30 MSFT @ $300 on IBKR (Jan 15), NBP 4.00
 *   Buy 50 MSFT @ $310 on Degiro (Feb 15), NBP 4.05
 *   Buy 20 MSFT @ $320 on Revolut (Mar 15), NBP 4.10
 *   Sell 70 MSFT @ $350 on IBKR (Jun 15), NBP 3.95, commission $0
 *
 * FIFO matching (cross-broker, per ISIN):
 *   CP1: 30 from IBKR buy (Jan, oldest)
 *   CP2: 40 from Degiro buy (Feb, next oldest -- partial fill)
 *
 * Hand-calculated CP1 (30 shares from IBKR buy):
 *   costBasis = 30 * 300 * 4.00 = 36000.00
 *   proceeds  = 30 * 350 * 3.95 = 41475.00
 *   buyComm   = 0 (zero commission for simplicity)
 *   sellComm  = 0
 *   gainLoss  = 41475 - 36000 - 0 - 0 = 5475.00
 *
 * Hand-calculated CP2 (40 shares from Degiro buy):
 *   costBasis = 40 * 310 * 4.05 = 50220.00
 *   proceeds  = 40 * 350 * 3.95 = 55300.00
 *   buyComm   = 0
 *   sellComm  = 0
 *   gainLoss  = 55300 - 50220 - 0 - 0 = 5080.00
 *
 * Remaining open:
 *   10 shares from Degiro buy (50 - 40 = 10)
 *   20 shares from Revolut buy
 *
 * Total gainLoss = 5475 + 5080 = 10555.00
 * taxBase = round(10555.00) = 10556 (art. 63: .00 -> 10555... wait, .00 floors to 10555)
 * Actually: 10555.00 rounds to 10555 (no fractional part)
 * tax = round(10555 * 0.19) = round(2005.45) = 2005 (.45 < .50 -> floor)
 *
 * @see art. 24 ust. 10 ustawy o PIT -- FIFO per instrument (cross-broker)
 */
final class GoldenDataset006CrossBrokerFIFOTest extends TestCase
{
    public function testCrossBrokerFIFOThreeBrokers(): void
    {
        // Single TaxPositionLedger per ISIN (cross-broker FIFO)
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US5949181045'), // MSFT
            TaxCategory::EQUITY,
        );

        // --- NBP rates ---
        $rateJan = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-14'),
            '009/A/NBP/2025',
        );
        $rateFeb = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-02-14'),
            '031/A/NBP/2025',
        );
        $rateMar = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.10'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
        $rateSell = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.95'),
            new \DateTimeImmutable('2025-06-13'),
            '114/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        // --- Buy #1: 30 MSFT on IBKR (Jan) ---
        $buyIbkrId = TransactionId::generate();
        $ledger->registerBuy(
            $buyIbkrId,
            new \DateTimeImmutable('2025-01-15'),
            BigDecimal::of('30'),
            Money::of('300.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rateJan,
            $converter,
        );

        // --- Buy #2: 50 MSFT on Degiro (Feb) ---
        $buyDegiroId = TransactionId::generate();
        $ledger->registerBuy(
            $buyDegiroId,
            new \DateTimeImmutable('2025-02-15'),
            BigDecimal::of('50'),
            Money::of('310.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('degiro'),
            $rateFeb,
            $converter,
        );

        // --- Buy #3: 20 MSFT on Revolut (Mar) ---
        $buyRevolutId = TransactionId::generate();
        $ledger->registerBuy(
            $buyRevolutId,
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('20'),
            Money::of('320.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rateMar,
            $converter,
        );

        // --- Sell: 70 MSFT on IBKR (Jun) ---
        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-06-15'),
            BigDecimal::of('70'),
            Money::of('350.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rateSell,
            $converter,
        );

        // FIFO produces 2 ClosedPositions (30 from IBKR + 40 from Degiro)
        self::assertCount(2, $closedPositions);

        // --- CP1: 30 shares from IBKR buy ---
        $cp1 = $closedPositions[0];

        self::assertTrue($cp1->buyTransactionId->equals($buyIbkrId));
        self::assertTrue($cp1->quantity->isEqualTo('30'));
        self::assertSame('ibkr', $cp1->buyBroker->toString());
        self::assertSame('ibkr', $cp1->sellBroker->toString());

        // costBasis = 30 * 300 * 4.00 = 36000.00
        self::assertTrue(
            $cp1->costBasisPLN->isEqualTo('36000.00'),
            "CP1 costBasis: 30 * 300 * 4.00 = 36000.00, got: {$cp1->costBasisPLN}",
        );

        // proceeds = 30 * 350 * 3.95 = 41475.00
        self::assertTrue(
            $cp1->proceedsPLN->isEqualTo('41475.00'),
            "CP1 proceeds: 30 * 350 * 3.95 = 41475.00, got: {$cp1->proceedsPLN}",
        );

        // gainLoss = 41475 - 36000 = 5475.00
        self::assertTrue(
            $cp1->gainLossPLN->isEqualTo('5475.00'),
            "CP1 gainLoss: 41475 - 36000 = 5475.00, got: {$cp1->gainLossPLN}",
        );

        // --- CP2: 40 shares from Degiro buy (partial fill) ---
        $cp2 = $closedPositions[1];

        self::assertTrue($cp2->buyTransactionId->equals($buyDegiroId));
        self::assertTrue($cp2->quantity->isEqualTo('40'));
        self::assertSame('degiro', $cp2->buyBroker->toString());
        self::assertSame('ibkr', $cp2->sellBroker->toString()); // sold on IBKR

        // costBasis = 40 * 310 * 4.05 = 50220.00
        self::assertTrue(
            $cp2->costBasisPLN->isEqualTo('50220.00'),
            "CP2 costBasis: 40 * 310 * 4.05 = 50220.00, got: {$cp2->costBasisPLN}",
        );

        // proceeds = 40 * 350 * 3.95 = 55300.00
        self::assertTrue(
            $cp2->proceedsPLN->isEqualTo('55300.00'),
            "CP2 proceeds: 40 * 350 * 3.95 = 55300.00, got: {$cp2->proceedsPLN}",
        );

        // gainLoss = 55300 - 50220 = 5080.00
        self::assertTrue(
            $cp2->gainLossPLN->isEqualTo('5080.00'),
            "CP2 gainLoss: 55300 - 50220 = 5080.00, got: {$cp2->gainLossPLN}",
        );

        // --- Remaining open positions ---
        $openPositions = $ledger->openPositions();
        self::assertCount(2, $openPositions, 'Should have 2 open positions (Degiro partial + Revolut)');

        // Degiro: 50 - 40 = 10 remaining
        self::assertTrue(
            $openPositions[0]->remainingQuantity()->isEqualTo('10'),
            "Degiro remaining: 50 - 40 = 10, got: {$openPositions[0]->remainingQuantity()}",
        );

        // Revolut: 20 remaining (untouched)
        self::assertTrue(
            $openPositions[1]->remainingQuantity()->isEqualTo('20'),
            "Revolut remaining: 20, got: {$openPositions[1]->remainingQuantity()}",
        );

        // ====================================================================
        // AnnualTaxCalculation
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // totalGainLoss = 5475 + 5080 = 10555.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('10555.00'),
            "equityGainLoss: 5475 + 5080 = 10555.00, got: {$calc->equityGainLoss()}",
        );

        // taxBase = round(10555.00) = 10555
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('10555'),
            "equityTaxBase: round(10555.00) = 10555, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(10555 * 0.19) = round(2005.45) = 2005 (.45 < .50 -> floor)
        self::assertTrue(
            $calc->equityTax()->isEqualTo('2005'),
            "equityTax: round(10555 * 0.19) = round(2005.45) = 2005, got: {$calc->equityTax()}",
        );
    }
}
