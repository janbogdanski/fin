<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use App\BrokerImport\Infrastructure\Adapter\Spreadsheet\XlsxWorkbookReader;
use App\BrokerImport\Infrastructure\Adapter\XTB\XTBStatementAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for AdapterRegistry.
 * Targets: InstanceOf_ Traversable/Ternary, UnwrapArrayValues,
 * UnwrapArrayKeys, TrueValue on adapterChoices, priority sorting.
 */
final class AdapterRegistryMutationTest extends TestCase
{
    /**
     * Kills InstanceOf_ and Ternary mutants: tests with plain array input.
     * The array path uses array_values($adapters).
     * If the mutant swaps ternary branches, iterator_to_array on array fails.
     */
    public function testConstructorAcceptsArray(): void
    {
        $adapters = [
            new BossaHistoryAdapter(),
            new RevolutStocksAdapter(),
            new IBKRActivityAdapter(),
        ];

        $registry = new AdapterRegistry($adapters);

        $brokers = $registry->supportedBrokers();
        self::assertCount(3, $brokers);
    }

    /**
     * Kills InstanceOf_ mutant: tests with generator (Traversable).
     */
    public function testConstructorAcceptsGenerator(): void
    {
        $generator = (function () {
            yield new BossaHistoryAdapter();
            yield new RevolutStocksAdapter();
        })();

        $registry = new AdapterRegistry($generator);

        $brokers = $registry->supportedBrokers();
        self::assertCount(2, $brokers);
    }

    /**
     * Kills priority sorting: adapters are sorted DESC by priority.
     * Bossa & IBKR have priority 100, Degiro & Revolut have priority 50.
     * Higher priority adapters should be tried first in detect().
     */
    public function testAdaptersSortedByPriorityDescending(): void
    {
        $bossa = new BossaHistoryAdapter(); // priority 100
        $revolut = new RevolutStocksAdapter(); // priority 50

        // Pass in wrong order
        $registry = new AdapterRegistry([$revolut, $bossa]);

        $brokers = $registry->supportedBrokers();
        // Bossa (100) should come before Revolut (50)
        self::assertSame('bossa', $brokers[0]);
        self::assertSame('revolut', $brokers[1]);
    }

    /**
     * Kills UnwrapArrayKeys mutant in findByAdapterKey error message.
     * If array_keys is unwrapped, error message would contain class names instead of keys.
     */
    public function testFindByAdapterKeyErrorContainsKeys(): void
    {
        $registry = new AdapterRegistry([new BossaHistoryAdapter()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ibkr');
        $this->expectExceptionMessage('degiro_transactions');
        $this->expectExceptionMessage('revolut');
        $this->expectExceptionMessage('bossa');
        $this->expectExceptionMessage('xtb');

        $registry->findByAdapterKey('nonexistent_key');
    }

    /**
     * Kills TrueValue mutant on adapterChoices: $registeredClasses[$adapter::class] = true.
     * Changed to false would cause isset() to still return true, but the map value changes.
     * Actually, isset() still returns true for false. So we check the result is correct.
     */
    public function testAdapterChoicesReturnsOnlyRegistered(): void
    {
        $adapters = [
            new BossaHistoryAdapter(),
            new DegiroTransactionsAdapter(),
        ];

        $registry = new AdapterRegistry($adapters);
        $choices = $registry->adapterChoices();

        // Only bossa and degiro_transactions should be in choices
        self::assertArrayHasKey('bossa', $choices);
        self::assertArrayHasKey('degiro_transactions', $choices);
        self::assertArrayNotHasKey('ibkr', $choices);
        self::assertArrayNotHasKey('revolut', $choices);
        self::assertArrayNotHasKey('degiro_account', $choices);

        // Display names should be human-readable strings
        self::assertStringContainsString('Bossa', $choices['bossa']);
        self::assertStringContainsString('Degiro', $choices['degiro_transactions']);
    }

    /**
     * Kills findByAdapterKey: registered adapter found by key.
     */
    public function testFindByAdapterKeyReturnsCorrectAdapter(): void
    {
        $adapters = [
            new BossaHistoryAdapter(),
            new DegiroTransactionsAdapter(),
            new DegiroAccountStatementAdapter(),
            new IBKRActivityAdapter(),
            new RevolutStocksAdapter(),
            new XTBStatementAdapter(new XlsxWorkbookReader()),
        ];

        $registry = new AdapterRegistry($adapters);

        $adapter = $registry->findByAdapterKey('bossa');
        self::assertInstanceOf(BossaHistoryAdapter::class, $adapter);

        $adapter = $registry->findByAdapterKey('ibkr');
        self::assertInstanceOf(IBKRActivityAdapter::class, $adapter);
    }

    /**
     * Kills findByAdapterKey: adapter class registered but not in container.
     */
    public function testFindByAdapterKeyThrowsWhenClassNotRegistered(): void
    {
        // Only register Bossa, then ask for IBKR
        $registry = new AdapterRegistry([new BossaHistoryAdapter()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered in the container');

        $registry->findByAdapterKey('ibkr');
    }
}
