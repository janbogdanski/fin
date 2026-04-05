<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use Brick\Math\BigDecimal;

final class NormalizedTransactionMother
{
    /**
     * Buy 10 shares of AAPL at $150 USD, $1 commission. IBKR broker.
     */
    public static function buyAAPL(
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: TransactionType::BUY,
            date: $date ?? new \DateTimeImmutable('2025-03-10'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: MoneyMother::usd('150.00'),
            commission: MoneyMother::usd('1.00'),
            broker: BrokerId::of('ibkr'),
            description: 'Buy 10 AAPL',
            rawData: [],
        );
    }

    /**
     * Sell 10 shares of AAPL at $170 USD, $1 commission. IBKR broker.
     */
    public static function sellAAPL(
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: TransactionType::SELL,
            date: $date ?? new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: MoneyMother::usd('170.00'),
            commission: MoneyMother::usd('1.00'),
            broker: BrokerId::of('ibkr'),
            description: 'Sell 10 AAPL',
            rawData: [],
        );
    }

    /**
     * Dividend from MSFT — $25 USD, zero commission.
     */
    public static function dividendMSFT(
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US5949181045'),
            symbol: 'MSFT',
            type: TransactionType::DIVIDEND,
            date: $date ?? new \DateTimeImmutable('2025-09-15'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: MoneyMother::usd('25.00'),
            commission: MoneyMother::usd('0.00'),
            broker: BrokerId::of('ibkr'),
            description: 'MSFT dividend',
            rawData: [],
        );
    }

    /**
     * Withholding tax entry — e.g. $5 USD WHT withheld on MSFT dividend.
     */
    public static function withWHT(
        Money $amount,
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US5949181045'),
            symbol: 'MSFT',
            type: TransactionType::WITHHOLDING_TAX,
            date: $date ?? new \DateTimeImmutable('2025-09-15'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: $amount,
            commission: MoneyMother::usd('0.00'),
            broker: BrokerId::of('ibkr'),
            description: 'MSFT WHT',
            rawData: [],
        );
    }

    /**
     * Broker fee transaction.
     */
    public static function withFee(
        Money $amount,
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: TransactionType::FEE,
            date: $date ?? new \DateTimeImmutable('2025-03-10'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: $amount,
            commission: MoneyMother::usd('0.00'),
            broker: BrokerId::of('ibkr'),
            description: 'Broker fee',
            rawData: [],
        );
    }

    /**
     * Corporate action transaction (e.g. stock split, merger).
     */
    public static function withCorporateAction(
        ?TransactionId $id = null,
        ?\DateTimeImmutable $date = null,
    ): NormalizedTransaction {
        return new NormalizedTransaction(
            id: $id ?? TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: TransactionType::CORPORATE_ACTION,
            date: $date ?? new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: MoneyMother::usd('0.00'),
            commission: MoneyMother::usd('0.00'),
            broker: BrokerId::of('ibkr'),
            description: 'Corporate action',
            rawData: [],
        );
    }
}
