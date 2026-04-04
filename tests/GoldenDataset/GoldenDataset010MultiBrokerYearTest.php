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
 * Golden Dataset #010 — Multiple brokers in one year (Rule #13).
 *
 * Note: GoldenDataset006 already covers 3-broker cross-broker FIFO for MSFT.
 * This test covers a complementary scenario: buy on IBKR, sell on Degiro (2 brokers, AAPL).
 * A third position is bought on Revolut and remains open (not sold).
 * Verifies that cross-broker FIFO correctly matches the IBKR buy against the Degiro sell.
 *
 * Scenario:
 *   Buy 20 AAPL @ $160 on IBKR (Jan 10), commission $0, NBP 4.00
 *   Buy 10 AAPL @ $180 on Revolut (Mar 10), commission $0, NBP 4.05 (remains open)
 *   Sell 20 AAPL @ $200 on Degiro (Sep 20), commission $0, NBP 3.90
 *
 * FIFO matching (cross-broker, per ISIN):
 *   CP1: 20 shares from IBKR buy (Jan 10, oldest first)
 *   Revolut buy (10 shares) remains open — not matched
 *
 * Hand-calculated CP1 (20 shares from IBKR buy):
 *   costBasis = 20 * 160 * 4.00 = 12800.00 PLN
 *   proceeds  = 20 * 200 * 3.90 = 15600.00 PLN
 *   buyComm   = 0.00 PLN
 *   sellComm  = 0.00 PLN
 *   gainLoss  = 15600.00 - 12800.00 = 2800.00 PLN
 *
 * Tax:
 *   taxBase = round(2800.00) = 2800
 *   tax     = round(2800 * 0.19) = round(532.00) = 532
 *
 * @see art. 24 ust. 10 ustawy o PIT — FIFO per instrument regardless of broker
 */
final class GoldenDataset010MultiBrokerYearTest extends TestCase
{
    public function testBuyIbkrSellDegiroCrossBrokerFIFO(): void
    {
        // Single TaxPositionLedger per ISIN — cross-broker FIFO
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        $rateJan = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-09'),
            '005/A/NBP/2025',
        );
        $rateMar = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-03-07'),
            '045/A/NBP/2025',
        );
        $rateSell = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.90'),
            new \DateTimeImmutable('2025-09-19'),
            '183/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        // --- Buy #1: 20 AAPL on IBKR (Jan 10) ---
        $buyIbkrId = TransactionId::generate();
        $ledger->registerBuy(
            $buyIbkrId,
            new \DateTimeImmutable('2025-01-10'),
            BigDecimal::of('20'),
            Money::of('160.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rateJan,
            $converter,
        );

        // --- Buy #2: 10 AAPL on Revolut (Mar 10) — will remain open ---
        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-10'),
            BigDecimal::of('10'),
            Money::of('180.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rateMar,
            $converter,
        );

        // --- Sell: 20 AAPL on Degiro (Sep 20) ---
        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('20'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('degiro'),
            $rateSell,
            $converter,
        );

        // FIFO matches only the IBKR buy (oldest)
        self::assertCount(1, $closedPositions);

        $cp = $closedPositions[0];

        self::assertTrue($cp->buyTransactionId->equals($buyIbkrId));
        self::assertTrue($cp->quantity->isEqualTo('20'));
        self::assertSame('ibkr', $cp->buyBroker->toString(), 'Buy broker must be ibkr');
        self::assertSame('degiro', $cp->sellBroker->toString(), 'Sell broker must be degiro (cross-broker)');

        // costBasis = 20 * 160 * 4.00 = 12800.00
        self::assertTrue(
            $cp->costBasisPLN->isEqualTo('12800.00'),
            "costBasis: 20 * 160 * 4.00 = 12800.00, got: {$cp->costBasisPLN}",
        );

        // proceeds = 20 * 200 * 3.90 = 15600.00
        self::assertTrue(
            $cp->proceedsPLN->isEqualTo('15600.00'),
            "proceeds: 20 * 200 * 3.90 = 15600.00, got: {$cp->proceedsPLN}",
        );

        // gainLoss = 15600.00 - 12800.00 - 0 - 0 = 2800.00
        self::assertTrue(
            $cp->gainLossPLN->isEqualTo('2800.00'),
            "gainLoss: 15600 - 12800 = 2800.00, got: {$cp->gainLossPLN}",
        );

        // --- Revolut position remains open ---
        $openPositions = $ledger->openPositions();
        self::assertCount(1, $openPositions, 'Revolut buy must remain open');
        self::assertTrue(
            $openPositions[0]->remainingQuantity()->isEqualTo('10'),
            "Revolut remaining: 10 shares, got: {$openPositions[0]->remainingQuantity()}",
        );

        // ====================================================================
        // AnnualTaxCalculation
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addClosedPositions($closedPositions, TaxCategory::EQUITY);
        $calc->finalize();

        // equityGainLoss = 2800.00
        self::assertTrue(
            $calc->equityGainLoss()->isEqualTo('2800.00'),
            "equityGainLoss: 2800.00, got: {$calc->equityGainLoss()}",
        );

        // taxBase = round(2800.00) = 2800
        self::assertTrue(
            $calc->equityTaxableIncome()->isEqualTo('2800'),
            "equityTaxBase: round(2800.00) = 2800, got: {$calc->equityTaxableIncome()}",
        );

        // tax = round(2800 * 0.19) = round(532.00) = 532
        self::assertTrue(
            $calc->equityTax()->isEqualTo('532'),
            "equityTax: round(2800 * 0.19) = round(532.00) = 532, got: {$calc->equityTax()}",
        );

        // totalTaxDue = 532
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('532'),
            "totalTaxDue: 532, got: {$calc->totalTaxDue()}",
        );
    }
}
