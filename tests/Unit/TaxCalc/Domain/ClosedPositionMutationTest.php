<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Tests\Factory\NBPRateMother;
use App\TaxCalc\Domain\Model\ClosedPosition;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for ClosedPosition.
 *
 * Targets: MethodCallRemoval on assertGainLossInvariant,
 * gainLoss tolerance check, and the invariant formula itself.
 */
final class ClosedPositionMutationTest extends TestCase
{
    /**
     * Kills MethodCallRemoval of assertGainLossInvariant.
     * With invariant removed, a wildly wrong gainLoss would be accepted.
     */
    public function testRejectsWrongGainLoss(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('gainLoss invariant violated');

        new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('1000.00'),
            proceedsPLN: BigDecimal::of('1200.00'),
            buyCommissionPLN: BigDecimal::of('5.00'),
            sellCommissionPLN: BigDecimal::of('5.00'),
            // Expected: 1200 - 1000 - 5 - 5 = 190.00. Providing 999.99 -> should throw.
            gainLossPLN: BigDecimal::of('999.99'),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: NBPRateMother::usd405(),
            sellNBPRate: NBPRateMother::usd405(),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }

    /**
     * Kills tolerance boundary mutations (isGreaterThan vs isGreaterThanOrEqualTo).
     * A difference of exactly 0.01 should pass (within tolerance).
     */
    public function testAcceptsGainLossWithinTolerance(): void
    {
        // Expected gainLoss = 1200 - 1000 - 5 - 5 = 190.00
        // Providing 190.01 (diff = 0.01 = tolerance) should be accepted
        $closed = new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('1000.00'),
            proceedsPLN: BigDecimal::of('1200.00'),
            buyCommissionPLN: BigDecimal::of('5.00'),
            sellCommissionPLN: BigDecimal::of('5.00'),
            gainLossPLN: BigDecimal::of('190.01'),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: NBPRateMother::usd405(),
            sellNBPRate: NBPRateMother::usd405(),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );

        self::assertTrue($closed->gainLossPLN->isEqualTo('190.01'));
    }

    /**
     * Verifies exact gainLoss computation works.
     */
    public function testAcceptsExactGainLoss(): void
    {
        // Expected: 1500 - 1000 - 10 - 10 = 480.00
        $closed = new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('1000.00'),
            proceedsPLN: BigDecimal::of('1500.00'),
            buyCommissionPLN: BigDecimal::of('10.00'),
            sellCommissionPLN: BigDecimal::of('10.00'),
            gainLossPLN: BigDecimal::of('480.00'),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: NBPRateMother::usd405(),
            sellNBPRate: NBPRateMother::usd405(),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );

        self::assertTrue($closed->gainLossPLN->isEqualTo('480.00'));
    }

    /**
     * Tests a loss scenario (proceeds < cost).
     */
    public function testAcceptsNegativeGainLoss(): void
    {
        // Expected: 800 - 1000 - 5 - 5 = -210.00
        $closed = new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: BigDecimal::of('1000.00'),
            proceedsPLN: BigDecimal::of('800.00'),
            buyCommissionPLN: BigDecimal::of('5.00'),
            sellCommissionPLN: BigDecimal::of('5.00'),
            gainLossPLN: BigDecimal::of('-210.00'),
            buyDate: new \DateTimeImmutable('2025-01-15'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: NBPRateMother::usd405(),
            sellNBPRate: NBPRateMother::usd405(),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );

        self::assertTrue($closed->gainLossPLN->isNegative());
    }
}
