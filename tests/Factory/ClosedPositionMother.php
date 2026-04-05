<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\TransactionId;
use App\TaxCalc\Domain\Model\ClosedPosition;
use Brick\Math\BigDecimal;

final class ClosedPositionMother
{
    /**
     * A standard closed position: 10 AAPL, bought at 6075 PLN, sold at 6885 PLN.
     * GainLoss = proceeds - costBasis - buyCommission - sellCommission
     *          = 6885 - 6075 - 4.05 - 4.05 = 801.90
     */
    public static function standard(
        ?TransactionId $buyId = null,
        ?TransactionId $sellId = null,
    ): ClosedPosition {
        $costBasis = BigDecimal::of('6075.00');
        $proceeds = BigDecimal::of('6885.00');
        $buyComm = BigDecimal::of('4.05');
        $sellComm = BigDecimal::of('4.05');
        $gainLoss = $proceeds->minus($costBasis)->minus($buyComm)->minus($sellComm);

        return new ClosedPosition(
            buyTransactionId: $buyId ?? TransactionId::generate(),
            sellTransactionId: $sellId ?? TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('10'),
            costBasisPLN: $costBasis,
            proceedsPLN: $proceeds,
            buyCommissionPLN: $buyComm,
            sellCommissionPLN: $sellComm,
            gainLossPLN: $gainLoss,
            buyDate: new \DateTimeImmutable('2025-03-10'),
            sellDate: new \DateTimeImmutable('2025-06-15'),
            buyNBPRate: NBPRateMother::usd405(new \DateTimeImmutable('2025-03-07')),
            sellNBPRate: NBPRateMother::usd405(new \DateTimeImmutable('2025-06-13')),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }

    /**
     * A position closed with a specified gain.
     * Cost=1000, Proceeds=1000+gain, zero commissions.
     */
    public static function withGain(string $amount): ClosedPosition
    {
        $costBasis = BigDecimal::of('1000.00');

        return self::make(
            costBasisPLN: $costBasis,
            proceedsPLN: $costBasis->plus(BigDecimal::of($amount)),
            gainLossPLN: BigDecimal::of($amount),
        );
    }

    /**
     * A position closed with a loss. Loss amount should be positive (e.g. '150.00').
     * Cost=1000, Proceeds=1000-loss, zero commissions, gainLoss is negative.
     */
    public static function withLoss(string $amount): ClosedPosition
    {
        $costBasis = BigDecimal::of('1000.00');
        $loss = BigDecimal::of($amount);

        return self::make(
            costBasisPLN: $costBasis,
            proceedsPLN: $costBasis->minus($loss),
            gainLossPLN: $loss->negated(),
        );
    }

    private static function make(
        BigDecimal $costBasisPLN,
        BigDecimal $proceedsPLN,
        BigDecimal $gainLossPLN,
    ): ClosedPosition {
        return new ClosedPosition(
            buyTransactionId: TransactionId::generate(),
            sellTransactionId: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            quantity: BigDecimal::of('1'),
            costBasisPLN: $costBasisPLN,
            proceedsPLN: $proceedsPLN,
            buyCommissionPLN: BigDecimal::zero(),
            sellCommissionPLN: BigDecimal::zero(),
            gainLossPLN: $gainLossPLN,
            buyDate: new \DateTimeImmutable('2025-01-10'),
            sellDate: new \DateTimeImmutable('2025-06-10'),
            buyNBPRate: NBPRateMother::usd405(),
            sellNBPRate: NBPRateMother::usd405(),
            buyBroker: BrokerId::of('ibkr'),
            sellBroker: BrokerId::of('ibkr'),
        );
    }
}
