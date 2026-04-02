<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportedTransactionRepositoryInterface;
use App\BrokerImport\Domain\Model\ImportedTransaction;
use App\BrokerImport\Infrastructure\Doctrine\DoctrineImportStorageAdapter;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DoctrineImportStorageAdapterTest extends TestCase
{
    private ImportedTransactionRepositoryInterface&MockObject $repository;

    private DoctrineImportStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ImportedTransactionRepositoryInterface::class);
        $this->adapter = new DoctrineImportStorageAdapter($this->repository);
    }

    public function testStoreConvertsNormalizedTransactionsToEntitiesAndPersists(): void
    {
        $userId = UserId::generate();
        $tx = $this->createNormalizedTransaction();

        $this->repository
            ->expects(self::once())
            ->method('saveAll')
            ->with(self::callback(function (array $entities) use ($userId): bool {
                self::assertCount(1, $entities);
                self::assertInstanceOf(ImportedTransaction::class, $entities[0]);
                self::assertTrue($entities[0]->userId->equals($userId));
                self::assertSame('BUY', $entities[0]->transactionType);

                return true;
            }));

        $batchId = $this->adapter->store($userId, 'ibkr', [$tx], 'abc123hash');

        self::assertNotEmpty($batchId);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $batchId);
    }

    public function testStoreWithEmptyTransactionsReturnsEmptyString(): void
    {
        $this->repository
            ->expects(self::never())
            ->method('saveAll');

        $batchId = $this->adapter->store(UserId::generate(), 'ibkr', [], 'abc123hash');

        self::assertSame('', $batchId);
    }

    public function testGetAllTransactionsConvertsEntitiesToDTOs(): void
    {
        $userId = UserId::generate();
        $entity = $this->createEntity($userId);

        $this->repository
            ->method('findByUser')
            ->with($userId)
            ->willReturn([$entity]);

        $transactions = $this->adapter->getAllTransactions($userId);

        self::assertCount(1, $transactions);
        self::assertInstanceOf(NormalizedTransaction::class, $transactions[0]);
        self::assertSame('AAPL', $transactions[0]->symbol);
        self::assertSame(TransactionType::BUY, $transactions[0]->type);
    }

    public function testGetBrokerCountDelegatesToRepository(): void
    {
        $userId = UserId::generate();

        $this->repository
            ->method('countBrokersByUser')
            ->with($userId)
            ->willReturn(3);

        self::assertSame(3, $this->adapter->getBrokerCount($userId));
    }

    public function testGetTotalTransactionCountDelegatesToRepository(): void
    {
        $userId = UserId::generate();

        $this->repository
            ->method('countByUser')
            ->with($userId)
            ->willReturn(42);

        self::assertSame(42, $this->adapter->getTotalTransactionCount($userId));
    }

    public function testGetClosedPositionCountDelegatesToRepository(): void
    {
        $userId = UserId::generate();

        $this->repository
            ->method('countSellsByUserAndYear')
            ->with($userId, 2025)
            ->willReturn(7);

        self::assertSame(7, $this->adapter->getClosedPositionCount($userId, 2025));
    }

    public function testWasAlreadyImportedDelegatesToRepository(): void
    {
        $userId = UserId::generate();

        $this->repository
            ->method('existsByContentHash')
            ->with($userId, 'somehash')
            ->willReturn(true);

        self::assertTrue($this->adapter->wasAlreadyImported($userId, 'somehash'));
    }

    public function testWasAlreadyImportedReturnsFalseWhenNotFound(): void
    {
        $userId = UserId::generate();

        $this->repository
            ->method('existsByContentHash')
            ->with($userId, 'newhash')
            ->willReturn(false);

        self::assertFalse($this->adapter->wasAlreadyImported($userId, 'newhash'));
    }

    private function createNormalizedTransaction(): NormalizedTransaction
    {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: TransactionType::BUY,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('150.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: 'Test transaction',
            rawData: [],
        );
    }

    private function createEntity(UserId $userId): ImportedTransaction
    {
        return new ImportedTransaction(
            id: TransactionId::generate(),
            userId: $userId,
            importBatchId: 'batch-123',
            broker: BrokerId::of('ibkr'),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            transactionType: TransactionType::BUY->value,
            date: new \DateTimeImmutable('2025-06-15'),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('150.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            description: 'Test transaction',
            contentHash: 'hash-456',
            createdAt: new \DateTimeImmutable(),
        );
    }
}
