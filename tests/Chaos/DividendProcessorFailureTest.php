<?php

declare(strict_types=1);

namespace App\Tests\Chaos;

use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\BrokerImport\Application\Port\FifoProcessorPort;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\Shared\Domain\Port\AuditLogPort;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\UserId;
use App\Tests\Factory\NormalizedTransactionMother;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Simulates DividendProcessorPort infrastructure failures:
 * - DividendProcessor throws RuntimeException → exception propagates from orchestration service
 *   (not swallowed silently)
 */
#[Group('chaos')]
final class DividendProcessorFailureTest extends TestCase
{
    public function testDividendProcessorFailurePropagatesFromOrchestrationService(): void
    {
        $tx = NormalizedTransactionMother::dividendMSFT();

        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('supports')->willReturn(true);
        $adapter->method('brokerId')->willReturn(BrokerId::of('ibkr'));
        $adapter->method('parse')->willReturn(new ParseResult(
            transactions: [$tx],
            errors: [],
            warnings: [],
            metadata: new ParseMetadata(
                broker: BrokerId::of('ibkr'),
                totalTransactions: 1,
                totalErrors: 0,
                dateFrom: new \DateTimeImmutable('2025-09-15'),
                dateTo: new \DateTimeImmutable('2025-09-15'),
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

        // FIFO has no sell transactions to process — it will not be called with persisted data
        $fifo = $this->createMock(FifoProcessorPort::class);

        $dividends = $this->createMock(DividendProcessorPort::class);
        $dividends->method('process')
            ->willThrowException(new \RuntimeException('Dividend calculation service unavailable'));

        $service = new ImportOrchestrationService(
            $detector,
            $storage,
            $fifo,
            $dividends,
            $this->createMock(AuditLogPort::class),
            $this->createMock(LoggerInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dividend calculation service unavailable');

        $service->import(
            UserId::generate(),
            'Date,ISIN,Quantity,Price',
            'test.csv',
        );
    }
}
