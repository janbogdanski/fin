<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\BrokerImport\Application\Port\FifoProcessorPort;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\BrokerImport\Domain\Exception\BrokerFileMismatchException;
use App\BrokerImport\Domain\Exception\ImportRowLimitExceededException;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\Shared\Domain\Port\AuditLogPort;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TransactionId;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Service\LedgerProcessingResult;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ImportOrchestrationServiceTest extends TestCase
{
    private BrokerDetectorPort&\PHPUnit\Framework\MockObject\MockObject $brokerDetector;

    private ImportStoragePort&\PHPUnit\Framework\MockObject\MockObject $importStorage;

    private FifoProcessorPort&\PHPUnit\Framework\MockObject\MockObject $fifoProcessor;

    private DividendProcessorPort&\PHPUnit\Framework\MockObject\MockObject $dividendProcessor;

    private AuditLogPort&\PHPUnit\Framework\MockObject\MockObject $auditLog;

    private LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger;

    private ImportOrchestrationService $service;

    protected function setUp(): void
    {
        $this->brokerDetector = $this->createMock(BrokerDetectorPort::class);
        $this->importStorage = $this->createMock(ImportStoragePort::class);
        $this->fifoProcessor = $this->createMock(FifoProcessorPort::class);
        $this->dividendProcessor = $this->createMock(DividendProcessorPort::class);
        $this->auditLog = $this->createMock(AuditLogPort::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ImportOrchestrationService(
            $this->brokerDetector,
            $this->importStorage,
            $this->fifoProcessor,
            $this->dividendProcessor,
            $this->auditLog,
            $this->logger,
        );
    }

    public function testWasAlreadyImportedDelegatesToStorage(): void
    {
        $userId = UserId::generate();
        $content = 'some,broker,file';
        $hash = hash('sha256', $content);

        $this->importStorage
            ->expects(self::once())
            ->method('wasAlreadyImported')
            ->with($userId, $hash)
            ->willReturn(true);

        self::assertTrue($this->service->wasAlreadyImported($userId, $content));
    }

    public function testWasAlreadyImportedReturnsFalseForNewContent(): void
    {
        $userId = UserId::generate();

        $this->importStorage
            ->method('wasAlreadyImported')
            ->willReturn(false);

        self::assertFalse($this->service->wasAlreadyImported($userId, 'new,broker,data'));
    }

    public function testImportWithEmptyTransactionsSkipsFifoAndDividends(): void
    {
        $userId = UserId::generate();

        $adapter = $this->createMockAdapter('ibkr', new ParseResult(
            transactions: [],
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);

        $this->importStorage->expects(self::never())->method('store');
        $this->fifoProcessor->expects(self::never())->method('process');
        $this->dividendProcessor->expects(self::never())->method('process');

        $this->importStorage->method('getTotalTransactionCount')->willReturn(0);
        $this->importStorage->method('getBrokerCount')->willReturn(0);

        $result = $this->service->import($userId, 'header1,header2', 'test.csv');

        self::assertSame(0, $result->importedCount);
        self::assertSame('ibkr', $result->brokerId);
        self::assertSame('Interactive Brokers (Activity Statement)', $result->brokerDisplayName);
        self::assertSame([], $result->fifoWarnings);
    }

    public function testImportStoresAndTriggersFifoForSellTransactions(): void
    {
        $userId = UserId::generate();
        $csvContent = 'broker,data,here';
        $contentHash = hash('sha256', $csvContent);

        $sellTx = $this->createTransaction(TransactionType::SELL, '2025-03-15');
        $buyTx = $this->createTransaction(TransactionType::BUY, '2025-01-10');
        $transactions = [$buyTx, $sellTx];

        $adapter = $this->createMockAdapter('ibkr', new ParseResult(
            transactions: $transactions,
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);

        $this->importStorage
            ->expects(self::once())
            ->method('store')
            ->with($userId, BrokerId::of('ibkr'), $transactions, $contentHash);

        $this->importStorage
            ->method('getAllTransactions')
            ->willReturn($transactions);

        $this->fifoProcessor
            ->expects(self::once())
            ->method('process')
            ->willReturn(new LedgerProcessingResult([], []));

        $this->dividendProcessor->expects(self::never())->method('process');

        $this->importStorage->method('getTotalTransactionCount')->willReturn(2);
        $this->importStorage->method('getBrokerCount')->willReturn(1);

        $result = $this->service->import($userId, $csvContent, 'ibkr.csv');

        self::assertSame(2, $result->importedCount);
        self::assertSame(2, $result->totalTransactionCount);
        self::assertSame(1, $result->brokerCount);
    }

    public function testImportTriggersDividendProcessingForDividendTransactions(): void
    {
        $userId = UserId::generate();

        $dividendTx = $this->createTransaction(TransactionType::DIVIDEND, '2025-06-15');

        $adapter = $this->createMockAdapter('revolut', new ParseResult(
            transactions: [$dividendTx],
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('revolut'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);
        $this->importStorage->method('getAllTransactions')->willReturn([$dividendTx]);
        $this->importStorage->method('getTotalTransactionCount')->willReturn(1);
        $this->importStorage->method('getBrokerCount')->willReturn(1);

        $this->fifoProcessor->expects(self::never())->method('process');
        $this->dividendProcessor->expects(self::once())->method('process');

        $this->service->import($userId, 'dividend,data', 'revolut.csv');
    }

    public function testImportCollectsFifoWarnings(): void
    {
        $userId = UserId::generate();

        $sellTx = $this->createTransaction(TransactionType::SELL, '2025-04-01');

        $adapter = $this->createMockAdapter('degiro', new ParseResult(
            transactions: [$sellTx],
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('degiro'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);
        $this->importStorage->method('getAllTransactions')->willReturn([$sellTx]);
        $this->importStorage->method('getTotalTransactionCount')->willReturn(1);
        $this->importStorage->method('getBrokerCount')->willReturn(1);

        $this->fifoProcessor
            ->method('process')
            ->willReturn(new LedgerProcessingResult([], ['ISIN US0378331005: Insufficient shares for sell']));

        $result = $this->service->import($userId, 'sell,without,buy', 'test.csv');

        self::assertCount(1, $result->fifoWarnings);
        self::assertStringContainsString('Insufficient shares', $result->fifoWarnings[0]);
    }

    public function testImportThrowsWhenBrokerNotRecognized(): void
    {
        $userId = UserId::generate();

        $this->brokerDetector
            ->method('detect')
            ->willThrowException(new UnsupportedBrokerFormatException('unknown.csv'));

        $this->expectException(UnsupportedBrokerFormatException::class);

        $this->service->import($userId, 'unknown,format', 'unknown.csv');
    }

    public function testImportWithAdapterUsesProvidedAdapter(): void
    {
        $userId = UserId::generate();
        $csvContent = 'broker,data,here';

        /** @var BrokerAdapterInterface&\PHPUnit\Framework\MockObject\MockObject $adapter */
        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('supports')->willReturn(true);
        $adapter->method('parse')->willReturn(new ParseResult(
            transactions: [],
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        // brokerDetector should NOT be called when using importWithAdapter
        $this->brokerDetector->expects(self::never())->method('detect');

        $this->importStorage->method('getTotalTransactionCount')->willReturn(0);
        $this->importStorage->method('getBrokerCount')->willReturn(0);

        $result = $this->service->importWithAdapter($userId, $csvContent, 'test.csv', $adapter);

        self::assertSame('ibkr', $result->brokerId);
    }

    public function testImportWithAdapterThrowsWhenFileDoesNotMatch(): void
    {
        $userId = UserId::generate();

        /** @var BrokerAdapterInterface&\PHPUnit\Framework\MockObject\MockObject $adapter */
        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('supports')->willReturn(false);

        $this->expectException(BrokerFileMismatchException::class);

        $this->service->importWithAdapter($userId, 'wrong,format', 'test.csv', $adapter);
    }

    public function testImportThrowsWhenRowLimitExceeded(): void
    {
        $userId = UserId::generate();

        // Build 5001 transactions (just over the limit)
        $transactions = [];
        for ($i = 0; $i < 5001; $i++) {
            $transactions[] = $this->createTransaction(TransactionType::BUY, '2025-01-10');
        }

        $adapter = $this->createMockAdapter('ibkr', new ParseResult(
            transactions: $transactions,
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);

        // Must NOT store any transactions
        $this->importStorage->expects(self::never())->method('store');

        // Must log to audit log and logger
        $this->auditLog->expects(self::once())->method('log')->with(
            'import.limit_exceeded',
            $userId->toString(),
            self::arrayHasKey('row_count'),
        );
        $this->logger->expects(self::once())->method('warning');

        $this->expectException(ImportRowLimitExceededException::class);

        $this->service->import($userId, 'csv', 'big_file.csv');
    }

    public function testImportWithAdapterThrowsWhenRowLimitExceeded(): void
    {
        $userId = UserId::generate();

        $transactions = [];
        for ($i = 0; $i < 5001; $i++) {
            $transactions[] = $this->createTransaction(TransactionType::BUY, '2025-01-10');
        }

        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('supports')->willReturn(true);
        $adapter->method('parse')->willReturn(new ParseResult(
            transactions: $transactions,
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        $this->importStorage->expects(self::never())->method('store');
        $this->auditLog->expects(self::once())->method('log');
        $this->expectException(ImportRowLimitExceededException::class);

        $this->service->importWithAdapter($userId, 'csv', 'big_file.csv', $adapter);
    }

    public function testImportDoesNotThrowAtExactLimit(): void
    {
        $userId = UserId::generate();

        $transactions = [];
        for ($i = 0; $i < 5000; $i++) {
            $transactions[] = $this->createTransaction(TransactionType::BUY, '2025-01-10');
        }

        $adapter = $this->createMockAdapter('ibkr', new ParseResult(
            transactions: $transactions,
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('ibkr'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);
        $this->importStorage->method('store')->willReturn('batch-id');
        $this->importStorage->method('getAllTransactions')->willReturn($transactions);
        $this->importStorage->method('getTotalTransactionCount')->willReturn(5000);
        $this->importStorage->method('getBrokerCount')->willReturn(1);
        $this->fifoProcessor->method('process')->willReturn(new LedgerProcessingResult([], []));

        // Should NOT throw
        $result = $this->service->import($userId, 'csv', 'exactly_limit.csv');

        self::assertSame(5000, $result->importedCount);
    }

    public function testUnknownBrokerIdFallsBackToUppercase(): void
    {
        $userId = UserId::generate();

        $adapter = $this->createMockAdapter('newbroker', new ParseResult(
            transactions: [],
            errors: [],
            warnings: [],
            metadata: $this->createMetadata('newbroker'),
        ));

        $this->brokerDetector->method('detect')->willReturn($adapter);
        $this->importStorage->method('getTotalTransactionCount')->willReturn(0);
        $this->importStorage->method('getBrokerCount')->willReturn(0);

        $result = $this->service->import($userId, 'csv', 'file.csv');

        self::assertSame('NEWBROKER', $result->brokerDisplayName);
    }

    private function createMockAdapter(string $brokerId, ParseResult $parseResult): BrokerAdapterInterface
    {
        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of($brokerId));
        $adapter->method('parse')->willReturn($parseResult);

        return $adapter;
    }

    private function createMetadata(string $brokerId): ParseMetadata
    {
        return new ParseMetadata(
            broker: BrokerId::of($brokerId),
            totalTransactions: 0,
            totalErrors: 0,
            dateFrom: null,
            dateTo: null,
            sectionsFound: [],
        );
    }

    private function createTransaction(TransactionType $type, string $date): NormalizedTransaction
    {
        return new NormalizedTransaction(
            id: TransactionId::generate(),
            isin: ISIN::fromString('US0378331005'),
            symbol: 'AAPL',
            type: $type,
            date: new \DateTimeImmutable($date),
            quantity: BigDecimal::of('10'),
            pricePerUnit: Money::of('150.00', CurrencyCode::USD),
            commission: Money::of('1.00', CurrencyCode::USD),
            broker: BrokerId::of('ibkr'),
            description: 'Test transaction',
            rawData: [],
        );
    }
}
