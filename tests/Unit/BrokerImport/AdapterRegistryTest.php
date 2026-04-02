<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\Shared\Domain\ValueObject\BrokerId;
use PHPUnit\Framework\TestCase;

final class AdapterRegistryTest extends TestCase
{
    public function testDetectsIbkrFormat(): void
    {
        $ibkrAdapter = $this->createAdapter('ibkr', supportsReturn: true);
        $degiroAdapter = $this->createAdapter('degiro', supportsReturn: false);

        $registry = new AdapterRegistry([$degiroAdapter, $ibkrAdapter]);

        $detected = $registry->detect('Interactive Brokers content', 'activity.csv');

        self::assertSame('ibkr', $detected->brokerId()->toString());
    }

    public function testDetectsFirstMatchingAdapter(): void
    {
        $first = $this->createAdapter('first', supportsReturn: true);
        $second = $this->createAdapter('second', supportsReturn: true);

        $registry = new AdapterRegistry([$first, $second]);

        $detected = $registry->detect('some content', 'file.csv');

        self::assertSame('first', $detected->brokerId()->toString());
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

    public function testReturnsSupportedBrokers(): void
    {
        $ibkr = $this->createAdapter('ibkr', supportsReturn: false);
        $degiro = $this->createAdapter('degiro', supportsReturn: false);

        $registry = new AdapterRegistry([$ibkr, $degiro]);

        self::assertSame(['ibkr', 'degiro'], $registry->supportedBrokers());
    }

    public function testReturnsSupportedBrokersEmptyWhenNoAdapters(): void
    {
        $registry = new AdapterRegistry([]);

        self::assertSame([], $registry->supportedBrokers());
    }

    private function createAdapter(string $brokerId, bool $supportsReturn): BrokerAdapterInterface
    {
        $adapter = $this->createMock(BrokerAdapterInterface::class);
        $adapter->method('brokerId')->willReturn(BrokerId::of($brokerId));
        $adapter->method('supports')->willReturn($supportsReturn);
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
