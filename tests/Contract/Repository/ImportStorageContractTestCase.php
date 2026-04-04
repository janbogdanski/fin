<?php

declare(strict_types=1);

namespace App\Tests\Contract\Repository;

use App\BrokerImport\Application\Port\ImportStoragePort;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Factory\NormalizedTransactionMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Abstract contract test for ImportStoragePort.
 *
 * Any implementation (InMemory, Doctrine) must satisfy these behavioral
 * contracts. Subclasses provide the concrete SUT via createStorage().
 */
abstract class ImportStorageContractTestCase extends KernelTestCase
{
    private ImportStoragePort $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createStorage();
    }

    // --- store() ---

    public function testStoreReturnsNonEmptyBatchId(): void
    {
        $userId = UserId::generate();
        $transactions = [NormalizedTransactionMother::buyAAPL()];

        $batchId = $this->storage->store($userId, 'ibkr', $transactions, 'hash-abc');

        self::assertNotEmpty($batchId);
    }

    public function testStoreEmptyTransactionsReturnsEmptyString(): void
    {
        $userId = UserId::generate();

        $batchId = $this->storage->store($userId, 'ibkr', [], 'hash-empty');

        self::assertSame('', $batchId);
    }

    public function testStoreMultipleBatchesReturnsDifferentIds(): void
    {
        $userId = UserId::generate();

        $batch1 = $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::buyAAPL()], 'hash-1');
        $batch2 = $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::sellAAPL()], 'hash-2');

        self::assertNotSame($batch1, $batch2);
    }

    // --- getAllTransactions() ---

    public function testGetAllTransactionsReturnsEmptyForNewUser(): void
    {
        $userId = UserId::generate();

        $result = $this->storage->getAllTransactions($userId);

        self::assertSame([], $result);
    }

    public function testGetAllTransactionsReturnsStoredTransactions(): void
    {
        $userId = UserId::generate();
        $buy = NormalizedTransactionMother::buyAAPL();
        $sell = NormalizedTransactionMother::sellAAPL();

        $this->storage->store($userId, 'ibkr', [$buy, $sell], 'hash-1');

        $result = $this->storage->getAllTransactions($userId);

        self::assertCount(2, $result);

        $ids = array_map(static fn ($tx) => $tx->id->toString(), $result);
        self::assertContains($buy->id->toString(), $ids);
        self::assertContains($sell->id->toString(), $ids);
    }

    public function testGetAllTransactionsIsolatedPerUser(): void
    {
        $user1 = UserId::generate();
        $user2 = UserId::generate();

        $this->storage->store($user1, 'ibkr', [NormalizedTransactionMother::buyAAPL()], 'hash-u1');
        $this->storage->store($user2, 'ibkr', [NormalizedTransactionMother::sellAAPL()], 'hash-u2');

        self::assertCount(1, $this->storage->getAllTransactions($user1));
        self::assertCount(1, $this->storage->getAllTransactions($user2));
    }

    // --- wasAlreadyImported() ---

    public function testWasAlreadyImportedReturnsFalseForNewUser(): void
    {
        self::assertFalse($this->storage->wasAlreadyImported(UserId::generate(), 'any-hash'));
    }

    public function testWasAlreadyImportedReturnsTrueAfterStore(): void
    {
        $userId = UserId::generate();
        $hash = 'content-hash-xyz';

        $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::buyAAPL()], $hash);

        self::assertTrue($this->storage->wasAlreadyImported($userId, $hash));
    }

    public function testWasAlreadyImportedIsolatedPerUser(): void
    {
        $user1 = UserId::generate();
        $user2 = UserId::generate();
        $hash = 'same-hash';

        $this->storage->store($user1, 'ibkr', [NormalizedTransactionMother::buyAAPL()], $hash);

        self::assertTrue($this->storage->wasAlreadyImported($user1, $hash));
        self::assertFalse($this->storage->wasAlreadyImported($user2, $hash));
    }

    public function testWasAlreadyImportedDistinguishesDifferentHashes(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::buyAAPL()], 'hash-A');

        self::assertTrue($this->storage->wasAlreadyImported($userId, 'hash-A'));
        self::assertFalse($this->storage->wasAlreadyImported($userId, 'hash-B'));
    }

    // --- getBrokerCount() ---

    public function testGetBrokerCountReturnsZeroForNewUser(): void
    {
        self::assertSame(0, $this->storage->getBrokerCount(UserId::generate()));
    }

    public function testGetBrokerCountCountsDistinctBrokers(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::buyAAPL()], 'hash-1');
        $this->storage->store($userId, 'degiro', [NormalizedTransactionMother::sellAAPL()], 'hash-2');

        self::assertSame(2, $this->storage->getBrokerCount($userId));
    }

    public function testGetBrokerCountDoesNotDoubleSameBroker(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::buyAAPL()], 'hash-1');
        $this->storage->store($userId, 'ibkr', [NormalizedTransactionMother::sellAAPL()], 'hash-2');

        self::assertSame(1, $this->storage->getBrokerCount($userId));
    }

    // --- getTotalTransactionCount() ---

    public function testGetTotalTransactionCountReturnsZeroForNewUser(): void
    {
        self::assertSame(0, $this->storage->getTotalTransactionCount(UserId::generate()));
    }

    public function testGetTotalTransactionCountSumsAcrossBatches(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [
            NormalizedTransactionMother::buyAAPL(),
            NormalizedTransactionMother::sellAAPL(),
        ], 'hash-1');

        $this->storage->store($userId, 'ibkr', [
            NormalizedTransactionMother::dividendMSFT(),
        ], 'hash-2');

        self::assertSame(3, $this->storage->getTotalTransactionCount($userId));
    }

    // --- getClosedPositionCount() ---

    public function testGetClosedPositionCountReturnsZeroForNewUser(): void
    {
        self::assertSame(0, $this->storage->getClosedPositionCount(UserId::generate(), 2025));
    }

    public function testGetClosedPositionCountCountsOnlySellsInYear(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [
            NormalizedTransactionMother::buyAAPL(date: new \DateTimeImmutable('2025-03-10')),
            NormalizedTransactionMother::sellAAPL(date: new \DateTimeImmutable('2025-06-15')),
            NormalizedTransactionMother::sellAAPL(date: new \DateTimeImmutable('2024-12-01')),
        ], 'hash-1');

        self::assertSame(1, $this->storage->getClosedPositionCount($userId, 2025));
        self::assertSame(1, $this->storage->getClosedPositionCount($userId, 2024));
        self::assertSame(0, $this->storage->getClosedPositionCount($userId, 2023));
    }

    public function testGetClosedPositionCountExcludesDividendsAndBuys(): void
    {
        $userId = UserId::generate();

        $this->storage->store($userId, 'ibkr', [
            NormalizedTransactionMother::buyAAPL(date: new \DateTimeImmutable('2025-01-10')),
            NormalizedTransactionMother::dividendMSFT(date: new \DateTimeImmutable('2025-03-15')),
        ], 'hash-div');

        self::assertSame(0, $this->storage->getClosedPositionCount($userId, 2025));
    }

    abstract protected function createStorage(): ImportStoragePort;
}
