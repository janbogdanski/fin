<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class ImportedTransactionTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $txId = TransactionId::generate();
        $userId = UserId::generate();
        $isin = ISIN::fromString('US0378331005');
        $broker = BrokerId::of('ibkr');
        $date = new \DateTimeImmutable('2025-06-15');
        $createdAt = new \DateTimeImmutable('2025-06-15 10:00:00');

        $entity = new ImportedTransaction(
            id: $txId,
            userId: $userId,
            importBatchId: 'batch-1',
            broker: $broker,
            isin: $isin,
            symbol: 'AAPL',
            transactionType: TransactionType::BUY->value,
            date: $date,
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('150.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            description: 'Buy AAPL',
            contentHash: 'hash-abc',
            createdAt: $createdAt,
        );

        self::assertTrue($entity->id->equals($txId));
        self::assertTrue($entity->userId->equals($userId));
        self::assertSame('batch-1', $entity->importBatchId);
        self::assertTrue($entity->broker->equals($broker));
        self::assertNotNull($entity->isin);
        self::assertTrue($entity->isin->equals($isin));
        self::assertSame('AAPL', $entity->symbol);
        self::assertSame('BUY', $entity->transactionType);
        self::assertSame('2025-06-15', $entity->date->format('Y-m-d'));
        self::assertTrue($entity->quantity->isEqualTo(BigDecimal::of('10')));
        self::assertTrue($entity->pricePerUnit->amount()->isEqualTo(BigDecimal::of('150.00')));
        self::assertSame(CurrencyCode::USD, $entity->pricePerUnit->currency());
        self::assertTrue($entity->commission->amount()->isEqualTo(BigDecimal::of('1.00')));
        self::assertSame('hash-abc', $entity->contentHash);
        self::assertSame($createdAt, $entity->createdAt);
    }

    public function testNullISINIsAllowed(): void
    {
        $entity = new ImportedTransaction(
            id: TransactionId::generate(),
            userId: UserId::generate(),
            importBatchId: 'batch-3',
            broker: BrokerId::of('ibkr'),
            isin: null,
            symbol: 'UNKNOWN',
            transactionType: TransactionType::FEE->value,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('1'),
            pricePerUnit: Money::of('10.00', CurrencyCode::USD),
            commission: Money::of('0.00', CurrencyCode::USD),
            description: 'Platform fee',
            contentHash: 'hash-ghi',
            createdAt: new \DateTimeImmutable(),
        );

        self::assertNull($entity->isin);
    }

    public function testAllTransactionTypesCanBeStored(): void
    {
        foreach (TransactionType::cases() as $type) {
            $entity = new ImportedTransaction(
                id: TransactionId::generate(),
                userId: UserId::generate(),
                importBatchId: 'batch-types',
                broker: BrokerId::of('ibkr'),
                isin: null,
                symbol: 'TEST',
                transactionType: $type->value,
                date: new \DateTimeImmutable('2025-06-15'),
                quantity: BigDecimal::of('1'),
                pricePerUnit: Money::of('100.00', CurrencyCode::USD),
                commission: Money::of('0.00', CurrencyCode::USD),
                description: 'Test ' . $type->value,
                contentHash: 'hash-' . $type->value,
                createdAt: new \DateTimeImmutable(),
            );

            self::assertSame($type->value, $entity->transactionType);
        }
    }
}
