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
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #002 — Cross-Year FIFO.
 *
 * Scenario:
 *   Buy 100 AAPL in 2024 (2024-06-15, $170, commission $1, NBP 3.98)
 *   Buy 50 AAPL in 2025 (2025-02-10, $180, commission $1, NBP 4.02)
 *   Sell 120 AAPL in 2025 (2025-09-20, $200, commission $2, NBP 3.95)
 *
 * FIFO matching:
 *   ClosedPosition 1: 100 from 2024 buy (oldest) @ sell 120
 *   ClosedPosition 2: 20 from 2025 buy @ sell 120
 *
 * 30 shares remain open from the 2025 buy.
 *
 * @see ADR-017 (Multi-Year FIFO)
 */
final class GoldenDataset002CrossYearFIFOTest extends TestCase
{
    public function testCrossYearFIFOProducesTwoClosedPositions(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'), // AAPL
            TaxCategory::EQUITY,
        );

        // --- Rates ---
        $rate2024 = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.98'),
            new \DateTimeImmutable('2024-06-14'),
            '115/A/NBP/2024',
        );
        $rate2025Feb = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.02'),
            new \DateTimeImmutable('2025-02-07'),
            '027/A/NBP/2025',
        );
        $rateSell = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('3.95'),
            new \DateTimeImmutable('2025-09-19'),
            '183/A/NBP/2025',
        );

        $converter = new CurrencyConverter();

        // --- Buy #1: 100 AAPL in 2024 ---
        $buy2024Id = TransactionId::generate();
        $ledger->registerBuy(
            $buy2024Id,
            new \DateTimeImmutable('2024-06-15'),
            BigDecimal::of('100'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate2024,
            $converter,
        );

        // --- Buy #2: 50 AAPL in 2025 ---
        $buy2025Id = TransactionId::generate();
        $ledger->registerBuy(
            $buy2025Id,
            new \DateTimeImmutable('2025-02-10'),
            BigDecimal::of('50'),
            Money::of('180.00', CurrencyCode::USD),
            Money::of('1.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate2025Feb,
            $converter,
        );

        // --- Sell: 120 AAPL in 2025 ---
        $closedPositions = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('120'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('2.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rateSell,
            $converter,
        );

        // FIFO produces exactly 2 ClosedPositions
        self::assertCount(2, $closedPositions);

        // --- ClosedPosition 1: 100 from 2024 buy ---
        $cp1 = $closedPositions[0];

        // FIFO: matched against 2024 buy (oldest)
        self::assertTrue($cp1->buyTransactionId->equals($buy2024Id));
        self::assertTrue($cp1->quantity->isEqualTo('100'));

        // Buy date from 2024, sell date from 2025 — cross-year
        self::assertSame('2024-06-15', $cp1->buyDate->format('Y-m-d'));
        self::assertSame('2025-09-20', $cp1->sellDate->format('Y-m-d'));

        // Buy NBP rate = 3.98 (from 2024)
        self::assertTrue($cp1->buyNBPRate->rate()->isEqualTo('3.98'));

        // Sell NBP rate = 3.95
        self::assertTrue($cp1->sellNBPRate->rate()->isEqualTo('3.95'));

        // costBasis = 100 * 170 * 3.98 = 67660.00
        self::assertTrue(
            $cp1->costBasisPLN->isEqualTo('67660.00'),
            "CP1 costBasis: 100 * 170 * 3.98 = 67660.00, got: {$cp1->costBasisPLN}",
        );

        // proceeds = 100 * 200 * 3.95 = 79000.00
        self::assertTrue(
            $cp1->proceedsPLN->isEqualTo('79000.00'),
            "CP1 proceeds: 100 * 200 * 3.95 = 79000.00, got: {$cp1->proceedsPLN}",
        );

        // buyCommission: total buy commission = $1 * 3.98 = 3.98 PLN for 100 shares
        // Per-unit commission = 3.98 / 100 = 0.0398
        // For 100 shares: 100 * 0.0398 = 3.98
        self::assertTrue(
            $cp1->buyCommissionPLN->isEqualTo('3.98'),
            "CP1 buyComm: 1 * 3.98 = 3.98, got: {$cp1->buyCommissionPLN}",
        );

        // sellCommission: total sell commission = $2 * 3.95 = 7.90 PLN for 120 shares
        // Per-unit sell commission = 7.90 / 120 = 0.06583333...
        // For 100 shares: 100 * 0.06583333... = 6.58 (rounded to 2dp)
        $expectedSellComm1 = BigDecimal::of('7.90')
            ->dividedBy('120', 8, \Brick\Math\RoundingMode::HALF_UP)
            ->multipliedBy('100')
            ->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        self::assertTrue(
            $cp1->sellCommissionPLN->isEqualTo($expectedSellComm1),
            "CP1 sellComm: (7.90/120)*100 rounded = {$expectedSellComm1}, got: {$cp1->sellCommissionPLN}",
        );

        // --- ClosedPosition 2: 20 from 2025 buy ---
        $cp2 = $closedPositions[1];

        // FIFO: matched against 2025 buy (next oldest)
        self::assertTrue($cp2->buyTransactionId->equals($buy2025Id));
        self::assertTrue($cp2->quantity->isEqualTo('20'));

        // Buy date from 2025 February
        self::assertSame('2025-02-10', $cp2->buyDate->format('Y-m-d'));
        self::assertSame('2025-09-20', $cp2->sellDate->format('Y-m-d'));

        // Buy NBP rate = 4.02 (from 2025)
        self::assertTrue($cp2->buyNBPRate->rate()->isEqualTo('4.02'));

        // costBasis = 20 * 180 * 4.02 = 14472.00
        self::assertTrue(
            $cp2->costBasisPLN->isEqualTo('14472.00'),
            "CP2 costBasis: 20 * 180 * 4.02 = 14472.00, got: {$cp2->costBasisPLN}",
        );

        // proceeds = 20 * 200 * 3.95 = 15800.00
        self::assertTrue(
            $cp2->proceedsPLN->isEqualTo('15800.00'),
            "CP2 proceeds: 20 * 200 * 3.95 = 15800.00, got: {$cp2->proceedsPLN}",
        );

        // buyCommission: $1 * 4.02 = 4.02 PLN for 50 shares
        // Per-unit = 4.02 / 50 = 0.0804
        // For 20 shares: 20 * 0.0804 = 1.608 -> 1.61 (rounded)
        $expectedBuyComm2 = BigDecimal::of('4.02')
            ->dividedBy('50', 8, \Brick\Math\RoundingMode::HALF_UP)
            ->multipliedBy('20')
            ->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        self::assertTrue(
            $cp2->buyCommissionPLN->isEqualTo($expectedBuyComm2),
            "CP2 buyComm: (4.02/50)*20 rounded = {$expectedBuyComm2}, got: {$cp2->buyCommissionPLN}",
        );

        // sellCommission for 20 shares: (7.90/120)*20 = 1.3166... -> 1.32 (rounded)
        $expectedSellComm2 = BigDecimal::of('7.90')
            ->dividedBy('120', 8, \Brick\Math\RoundingMode::HALF_UP)
            ->multipliedBy('20')
            ->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
        self::assertTrue(
            $cp2->sellCommissionPLN->isEqualTo($expectedSellComm2),
            "CP2 sellComm: (7.90/120)*20 rounded = {$expectedSellComm2}, got: {$cp2->sellCommissionPLN}",
        );

        // --- Remaining open positions ---
        // 30 shares from 2025 buy should remain
        $openPositions = $ledger->openPositions();
        self::assertCount(1, $openPositions);
        self::assertTrue(
            $openPositions[0]->remainingQuantity()->isEqualTo('30'),
            '30 shares remaining from 2025 buy (50 bought - 20 sold)',
        );
    }
}
