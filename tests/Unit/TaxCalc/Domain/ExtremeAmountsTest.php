<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Model\TaxPositionLedger;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\CurrencyConverterInterface;
use App\TaxCalc\Domain\Service\DividendTaxService;
use App\TaxCalc\Domain\Service\UPORegistry;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * P3-004/P3-005: Tests for extreme amounts (very large and very small)
 * in FIFO matching and tax calculation.
 */
final class ExtremeAmountsTest extends TestCase
{
    private CurrencyConverterInterface $converter;

    protected function setUp(): void
    {
        $this->converter = new CurrencyConverter();
    }

    /**
     * P3-004: Very large amount — 10M PLN equivalent trade.
     * Buy 10000 shares @ $250 (= $2.5M, ~10M PLN at 4.0 rate).
     */
    public function testFIFOWithVeryLargeAmount(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $buyRate = $this->nbpRate('4.00', '2025-03-14', '052/A/NBP/2025');
        $sellRate = $this->nbpRate('4.10', '2025-09-19', '183/A/NBP/2025');

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('10000'),
            Money::of('250.00', CurrencyCode::USD),
            Money::of('10.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('10000'),
            Money::of('260.00', CurrencyCode::USD),
            Money::of('10.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
            $this->converter,
        );

        self::assertCount(1, $closed);

        $cp = $closed[0];

        // Cost basis: 10000 * 250 * 4.00 = 10,000,000.00
        self::assertTrue($cp->costBasisPLN->isEqualTo('10000000.00'));

        // Proceeds per unit: 260 * 4.10 = 1066.00 per share
        // Proceeds total: 10000 * 260 * 4.10 = 10,660,000.00
        self::assertTrue($cp->proceedsPLN->isEqualTo('10660000.00'));

        // Gain should be positive and large
        self::assertTrue($cp->gainLossPLN->isPositive());
    }

    /**
     * P3-004: Very large amount in AnnualTaxCalculation.
     */
    public function testAnnualTaxCalculationWithLargeAmount(): void
    {
        $calc = AnnualTaxCalculation::create(
            UserId::generate(),
            TaxYear::of(2025),
        );

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $buyRate = $this->nbpRate('4.00', '2025-03-14', '052/A/NBP/2025');
        $sellRate = $this->nbpRate('4.10', '2025-09-19', '183/A/NBP/2025');

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('10000'),
            Money::of('250.00', CurrencyCode::USD),
            Money::of('10.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $buyRate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('10000'),
            Money::of('260.00', CurrencyCode::USD),
            Money::of('10.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $sellRate,
            $this->converter,
        );

        $calc->addClosedPositions($closed, TaxCategory::EQUITY);
        $snapshot = $calc->finalize();

        // Tax on ~660k PLN gain at 19%
        self::assertTrue($snapshot->equityTax->isPositive());
        self::assertTrue($snapshot->totalTaxDue->isPositive());

        // Sanity: tax should not exceed proceeds
        self::assertTrue($snapshot->equityTax->isLessThanOrEqualTo($snapshot->equityProceeds));
    }

    /**
     * P3-005: Very small amount — 0.01 PLN equivalent.
     * Buy 1 share @ $0.01 (fractional penny stock).
     */
    public function testFIFOWithVerySmallAmount(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $rate = $this->nbpRate('4.00', '2025-03-14', '052/A/NBP/2025');

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('1'),
            Money::of('0.01', CurrencyCode::USD),
            Money::of('0.01', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('1'),
            Money::of('0.02', CurrencyCode::USD),
            Money::of('0.01', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $this->converter,
        );

        self::assertCount(1, $closed);

        $cp = $closed[0];

        // Cost basis: 1 * 0.01 * 4.00 = 0.04
        self::assertTrue($cp->costBasisPLN->isEqualTo('0.04'));

        // Proceeds: 1 * 0.02 * 4.00 = 0.08
        self::assertTrue($cp->proceedsPLN->isEqualTo('0.08'));

        // Commission: 0.01 * 4.00 = 0.04
        self::assertTrue($cp->buyCommissionPLN->isEqualTo('0.04'));
    }

    /**
     * P3-005: Very small amount in AnnualTaxCalculation — no overflow/underflow.
     */
    public function testAnnualTaxCalculationWithSmallAmount(): void
    {
        $calc = AnnualTaxCalculation::create(
            UserId::generate(),
            TaxYear::of(2025),
        );

        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $rate = $this->nbpRate('4.00', '2025-03-14', '052/A/NBP/2025');

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('1'),
            Money::of('0.01', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('1'),
            Money::of('0.02', CurrencyCode::USD),
            Money::of('0.00', CurrencyCode::USD),
            BrokerId::of('ibkr'),
            $rate,
            $this->converter,
        );

        $calc->addClosedPositions($closed, TaxCategory::EQUITY);
        $snapshot = $calc->finalize();

        // Gain: 0.08 - 0.04 = 0.04 PLN. Tax at 19% = ~0.0076 -> rounds to 0
        self::assertFalse($snapshot->equityTax->isNegative());
        self::assertFalse($snapshot->totalTaxDue->isNegative());
    }

    /**
     * P3-005: Fractional share (0.001 qty) at small price.
     */
    public function testFIFOWithFractionalShares(): void
    {
        $ledger = TaxPositionLedger::create(
            UserId::generate(),
            ISIN::fromString('US0378331005'),
            TaxCategory::EQUITY,
        );

        $rate = $this->nbpRate('4.00', '2025-03-14', '052/A/NBP/2025');

        $ledger->registerBuy(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-03-15'),
            BigDecimal::of('0.001'),
            Money::of('170.00', CurrencyCode::USD),
            Money::of('0.01', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rate,
            $this->converter,
        );

        $closed = $ledger->registerSell(
            TransactionId::generate(),
            new \DateTimeImmutable('2025-09-20'),
            BigDecimal::of('0.001'),
            Money::of('200.00', CurrencyCode::USD),
            Money::of('0.01', CurrencyCode::USD),
            BrokerId::of('revolut'),
            $rate,
            $this->converter,
        );

        self::assertCount(1, $closed);
        self::assertTrue($closed[0]->quantity->isEqualTo('0.001'));
        // Cost: 0.001 * 170 * 4 = 0.68 PLN
        self::assertTrue($closed[0]->costBasisPLN->isEqualTo('0.68'));
    }

    /**
     * P3-004: Dividend tax calculation with very large gross amount.
     */
    public function testDividendTaxWithLargeAmount(): void
    {
        $upoRegistry = new UPORegistry();
        $service = new DividendTaxService($upoRegistry, $this->converter);
        $nbpRate = $this->nbpRate('4.00', '2025-06-15', '115/A/NBP/2025');

        $result = $service->calculate(
            grossDividend: Money::of('1000000', CurrencyCode::USD),
            nbpRate: $nbpRate,
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('0.15'),
        );

        // Gross PLN: 1M * 4.0 = 4M
        self::assertTrue($result->grossDividendPLN->amount()->isEqualTo('4000000'));

        // WHT PLN: 4M * 0.15 = 600k
        self::assertTrue($result->whtPaidPLN->amount()->isEqualTo('600000'));

        // Polish tax due should not be negative
        self::assertFalse($result->polishTaxDue->amount()->isNegative());
    }

    private function nbpRate(string $rate, string $date, string $tableNumber): NBPRate
    {
        return NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of($rate),
            new \DateTimeImmutable($date),
            $tableNumber,
        );
    }
}
