<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use App\BrokerImport\Infrastructure\Adapter\Spreadsheet\XlsxWorkbookReader;
use App\BrokerImport\Infrastructure\Adapter\XTB\XTBStatementAdapter;
use App\Shared\Domain\ValueObject\BrokerId;
use PHPUnit\Framework\TestCase;

final class AdapterRegistryTest extends TestCase
{
    public function testDetectsIbkrFormat(): void
    {
        $ibkrAdapter = $this->createAdapter('ibkr', supportsReturn: true, priority: 100);
        $degiroAdapter = $this->createAdapter('degiro', supportsReturn: false, priority: 50);

        $registry = new AdapterRegistry([$degiroAdapter, $ibkrAdapter]);

        $detected = $registry->detect('Interactive Brokers content', 'activity.csv');

        self::assertSame('ibkr', $detected->brokerId()->toString());
    }

    public function testDetectsHighestPriorityMatchingAdapter(): void
    {
        $lowPriority = $this->createAdapter('low', supportsReturn: true, priority: 50);
        $highPriority = $this->createAdapter('high', supportsReturn: true, priority: 100);

        // Passed in low-priority-first order — registry should sort by priority DESC
        $registry = new AdapterRegistry([$lowPriority, $highPriority]);

        $detected = $registry->detect('some content', 'file.csv');

        self::assertSame('high', $detected->brokerId()->toString());
    }

    public function testThrowsOnUnsupportedFormat(): void
    {
        $adapter = $this->createAdapter('ibkr', supportsReturn: false);

        $registry = new AdapterRegistry([$adapter]);

        $this->expectException(UnsupportedBrokerFormatException::class);
        $this->expectExceptionMessage('unknown.csv');

        $registry->detect('random content', 'unknown.csv');
    }

    public function testThrowsWhenNoAdaptersRegistered(): void
    {
        $registry = new AdapterRegistry([]);

        $this->expectException(UnsupportedBrokerFormatException::class);

        $registry->detect('content', 'file.csv');
    }

    public function testSupportedBrokersOrderedByPriority(): void
    {
        $ibkr = $this->createAdapter('ibkr', supportsReturn: false, priority: 100);
        $degiro = $this->createAdapter('degiro', supportsReturn: false, priority: 50);

        // Passed in wrong order — registry should sort
        $registry = new AdapterRegistry([$degiro, $ibkr]);

        self::assertSame(['ibkr', 'degiro'], $registry->supportedBrokers());
    }

    public function testReturnsSupportedBrokersEmptyWhenNoAdapters(): void
    {
        $registry = new AdapterRegistry([]);

        self::assertSame([], $registry->supportedBrokers());
    }

    public function testFindByAdapterKeyReturnsCorrectAdapter(): void
    {
        $registry = new AdapterRegistry([
            new IBKRActivityAdapter(),
            new DegiroTransactionsAdapter(),
            new DegiroAccountStatementAdapter(),
            new RevolutStocksAdapter(),
            new BossaHistoryAdapter(),
            new XTBStatementAdapter(new XlsxWorkbookReader()),
        ]);

        $ibkr = $registry->findByAdapterKey('ibkr');
        self::assertInstanceOf(IBKRActivityAdapter::class, $ibkr);

        $degiroTx = $registry->findByAdapterKey('degiro_transactions');
        self::assertInstanceOf(DegiroTransactionsAdapter::class, $degiroTx);

        $degiroAcc = $registry->findByAdapterKey('degiro_account');
        self::assertInstanceOf(DegiroAccountStatementAdapter::class, $degiroAcc);

        $revolut = $registry->findByAdapterKey('revolut');
        self::assertInstanceOf(RevolutStocksAdapter::class, $revolut);

        $bossa = $registry->findByAdapterKey('bossa');
        self::assertInstanceOf(BossaHistoryAdapter::class, $bossa);

        $xtb = $registry->findByAdapterKey('xtb');
        self::assertInstanceOf(XTBStatementAdapter::class, $xtb);
    }

    public function testFindByAdapterKeyThrowsForUnknownKey(): void
    {
        $registry = new AdapterRegistry([new IBKRActivityAdapter()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown adapter key "nonexistent"');

        $registry->findByAdapterKey('nonexistent');
    }

    public function testFindByAdapterKeyThrowsWhenAdapterNotRegistered(): void
    {
        // Registry has no adapters, but the key exists in the map
        $registry = new AdapterRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered in the container');

        $registry->findByAdapterKey('ibkr');
    }

    public function testAdapterChoicesReturnsRegisteredAdaptersOnly(): void
    {
        $registry = new AdapterRegistry([
            new IBKRActivityAdapter(),
            new RevolutStocksAdapter(),
            new XTBStatementAdapter(new XlsxWorkbookReader()),
        ]);

        $choices = $registry->adapterChoices();

        self::assertArrayHasKey('ibkr', $choices);
        self::assertArrayHasKey('revolut', $choices);
        self::assertArrayHasKey('xtb', $choices);
        self::assertArrayNotHasKey('degiro_transactions', $choices);
        self::assertArrayNotHasKey('degiro_account', $choices);
        self::assertArrayNotHasKey('bossa', $choices);
    }

    public function testAdapterChoicesReturnsAllWhenAllRegistered(): void
    {
        $registry = new AdapterRegistry([
            new IBKRActivityAdapter(),
            new DegiroTransactionsAdapter(),
            new DegiroAccountStatementAdapter(),
            new RevolutStocksAdapter(),
            new BossaHistoryAdapter(),
            new XTBStatementAdapter(new XlsxWorkbookReader()),
        ]);

        $choices = $registry->adapterChoices();

        self::assertCount(6, $choices);
        self::assertArrayHasKey('ibkr', $choices);
        self::assertArrayHasKey('degiro_transactions', $choices);
        self::assertArrayHasKey('degiro_account', $choices);
        self::assertArrayHasKey('revolut', $choices);
        self::assertArrayHasKey('bossa', $choices);
        self::assertArrayHasKey('xtb', $choices);

        // Verify display names contain useful info
        self::assertStringContainsString('Interactive Brokers', $choices['ibkr']);
        self::assertStringContainsString('Degiro', $choices['degiro_transactions']);
        self::assertStringContainsString('Account Statement', $choices['degiro_account']);
        self::assertStringContainsString('XTB', $choices['xtb']);
    }

    public function testAdapterChoicesReturnsEmptyWhenNoAdapters(): void
    {
        $registry = new AdapterRegistry([]);

        self::assertSame([], $registry->adapterChoices());
    }

    private function createAdapter(string $brokerId, bool $supportsReturn, int $priority = 50): BrokerAdapterInterface
    {
        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of($brokerId));
        $adapter->method('supports')->willReturn($supportsReturn);
        $adapter->method('priority')->willReturn($priority);
        $adapter->method('parse')->willReturn(
            new ParseResult(
                transactions: [],
                errors: [],
                warnings: [],
                metadata: new ParseMetadata(
                    broker: BrokerId::of($brokerId),
                    totalTransactions: 0,
                    totalErrors: 0,
                    dateFrom: null,
                    dateTo: null,
                    sectionsFound: [],
                ),
            ),
        );

        return $adapter;
    }
}
