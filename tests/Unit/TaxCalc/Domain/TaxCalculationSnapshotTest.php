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
use App\TaxCalc\Domain\Model\ClosedPosition;
use App\TaxCalc\Domain\Model\TaxCalculationSnapshot;
use App\TaxCalc\Domain\ValueObject\DividendTaxResult;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Tests that finalize() returns a correct TaxCalculationSnapshot DTO.
 */
final class TaxCalculationSnapshotTest extends TestCase
{
    public function testFinalizeReturnsSnapshot(): void
    {
        $userId = UserId::generate();
        $taxYear = TaxYear::of(2025);

        $calc = AnnualTaxCalculation::create($userId, $taxYear);

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '20000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '10000.00')],
            TaxCategory::EQUITY,
        );

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '15000.00', costBasis: '10000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '5000.00')],
            TaxCategory::CRYPTO,
        );

        $nbpRate = NBPRate::create(CurrencyCode::USD, BigDecimal::of('4.00'), new \DateTimeImmutable('2025-06-01'), '110/A/NBP/2025');
        $calc->addDividendResult(new DividendTaxResult(
            grossDividendPLN: Money::of('1000.00', CurrencyCode::PLN),
            whtPaidPLN: Money::of('90.00', CurrencyCode::PLN),
            whtRate: BigDecimal::of('0.15'),
            upoRate: BigDecimal::of('0.15'),
            polishTaxDue: Money::of('100.00', CurrencyCode::PLN),
            sourceCountry: CountryCode::US,
            nbpRate: $nbpRate,
        ));

        $snapshot = $calc->finalize();

        self::assertInstanceOf(TaxCalculationSnapshot::class, $snapshot);

        // Identity
        self::assertTrue($snapshot->userId->equals($userId));
        self::assertTrue($snapshot->taxYear->equals($taxYear));

        // Equity
        self::assertTrue($snapshot->equityProceeds->isEqualTo('20000.00'));
        self::assertTrue($snapshot->equityCostBasis->isEqualTo('10000.00'));
        self::assertTrue($snapshot->equityGainLoss->isEqualTo('10000.00'));
        self::assertTrue($snapshot->equityTaxableIncome->isEqualTo('10000'));
        self::assertTrue($snapshot->equityTax->isEqualTo('1900'));

        // Crypto
        self::assertTrue($snapshot->cryptoProceeds->isEqualTo('15000.00'));
        self::assertTrue($snapshot->cryptoCostBasis->isEqualTo('10000.00'));
        self::assertTrue($snapshot->cryptoGainLoss->isEqualTo('5000.00'));
        self::assertTrue($snapshot->cryptoTaxableIncome->isEqualTo('5000'));
        self::assertTrue($snapshot->cryptoTax->isEqualTo('950'));

        // Dividends
        self::assertCount(1, $snapshot->dividendsByCountry);
        self::assertArrayHasKey('US', $snapshot->dividendsByCountry);
        self::assertTrue($snapshot->dividendTotalTaxDue->isEqualTo('100.00'));

        // Total: 1900 + 100 + 950 = 2950
        self::assertTrue($snapshot->totalTaxDue->isEqualTo('2950.00'));
    }

    public function testToSnapshotReflectsCurrentState(): void
    {
        $calc = AnnualTaxCalculation::create(UserId::generate(), TaxYear::of(2025));

        $calc->addClosedPositions(
            [$this->closedPosition(proceeds: '5000.00', costBasis: '3000.00', buyComm: '0.00', sellComm: '0.00', gainLoss: '2000.00')],
            TaxCategory::EQUITY,
        );

        // Snapshot before finalize — shows raw state
        $preSnapshot = $calc->toSnapshot();
        self::assertTrue($preSnapshot->equityGainLoss->isEqualTo('2000.00'));
        self::assertTrue($preSnapshot->equityTaxableIncome->isZero()); // not yet calculated

        // After finalize
        $postSnapshot = $calc->finalize();
        self::assertTrue($postSnapshot->equityTaxableIncome->isEqualTo('2000'));
        self::assertTrue($postSnapshot->equityTax->isEqualTo('380'));
    }

    private function closedPosition(
        string $proceeds,
        string $costBasis,
        string $buyComm,
        string $sellComm,
        string $gainLoss,
    ): ClosedPosition {
        $nbpRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-01-14'),
            '009/A/NBP/2025',
        );

        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('100'),
            costBasisPLN: BigDecimal::of($costBasis),
            proceedsPLN: BigDecimal::of($proceeds),
            buyCommissionPLN: BigDecimal::of($buyComm),
            sellCommissionPLN: BigDecimal::of($sellComm),
            gainLossPLN: BigDecimal::of($gainLoss),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-20'),
            buyNBPRate: $nbpRate,
            sellNBPRate: $nbpRate,
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
