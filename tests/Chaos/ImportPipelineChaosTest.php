<?php

declare(strict_types=1);

namespace App\Tests\Chaos;

use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\BrokerImport\Application\Port\FifoProcessorPort;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Factory\NormalizedTransactionMother;
use PHPUnit\Framework\TestCase;

/**
 * @group chaos
 *
 * Simulates infrastructure failures during the CSV import pipeline:
 * - Storage adapter throws during persistence
 * - FIFO processor fails mid-computation
 */
final class ImportPipelineChaosTest extends TestCase
{
    public function testStorageFailureDuringImportBubbles(): void
    {
        $tx = NormalizedTransactionMother::buyAAPL();

        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('supports')->willReturn(true);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('parse')->willReturn(new ParseResult(
            transactions: [$tx],
            errors: [],
            warnings: [],
            metadata: new \App\BrokerImport\Application\DTO\ParseMetadata(
                broker: BrokerId::of('ibkr'),
                totalTransactions: 1,
                totalErrors: 0,
                dateFrom: new \DateTimeImmutable('2025-03-10'),
                dateTo: new \DateTimeImmutable('2025-03-10'),
                sectionsFound: [],
            ),
        ));

        $detector = $this->createMock(BrokerDetectorPort::class);
        $detector->method('detect')->willReturn($adapter);

        $storage = $this->createMock(ImportStoragePort::class);
        $storage->method('wasAlreadyImported')->willReturn(false);
        $storage->method('store')->willThrowException(
            new \RuntimeException('Database connection lost during INSERT'),
        );

        $fifo = $this->createMock(FifoProcessorPort::class);
        $dividends = $this->createMock(DividendProcessorPort::class);

        $service = new ImportOrchestrationService($detector, $storage, $fifo, $dividends);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $service->import(
            UserId::generate(),
            'Date,ISIN,Quantity,Price',
            'test.csv',
        );
    }

    public function testFifoProcessorFailureAfterStorageBubbles(): void
    {
        $tx = NormalizedTransactionMother::sellAAPL();

        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('supports')->willReturn(true);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('parse')->willReturn(new ParseResult(
            transactions: [$tx],
            errors: [],
            warnings: [],
            metadata: new \App\BrokerImport\Application\DTO\ParseMetadata(
                broker: BrokerId::of('ibkr'),
                totalTransactions: 1,
                totalErrors: 0,
                dateFrom: new \DateTimeImmutable('2025-03-10'),
                dateTo: new \DateTimeImmutable('2025-03-10'),
                sectionsFound: [],
            ),
        ));

        $detector = $this->createMock(BrokerDetectorPort::class);
        $detector->method('detect')->willReturn($adapter);

        $storage = $this->createMock(ImportStoragePort::class);
        $storage->method('wasAlreadyImported')->willReturn(false);
        $storage->method('getAllTransactions')->willReturn([$tx]);
        $storage->method('getTotalTransactionCount')->willReturn(1);
        $storage->method('getBrokerCount')->willReturn(1);

        $fifo = $this->createMock(FifoProcessorPort::class);
        $fifo->method('process')->willThrowException(
            new \RuntimeException('FIFO computation out of memory'),
        );

        $dividends = $this->createMock(DividendProcessorPort::class);

        $service = new ImportOrchestrationService($detector, $storage, $fifo, $dividends);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('out of memory');

        $service->import(
            UserId::generate(),
            'Date,ISIN,Quantity,Price',
            'test.csv',
        );
    }
}
