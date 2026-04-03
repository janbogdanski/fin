<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: loads all fixture CSVs through the full AdapterRegistry
 * pipeline (detect + parse) with real adapter instances.
 *
 * Verifies that:
 * - detect() selects the correct adapter for each fixture
 * - parse() returns non-empty transactions for valid CSVs
 */
final class AdapterRegistryIntegrationTest extends TestCase
{
    private AdapterRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AdapterRegistry([
            new IBKRActivityAdapter(),
            new DegiroTransactionsAdapter(),
            new DegiroAccountStatementAdapter(),
            new RevolutStocksAdapter(),
            new BossaHistoryAdapter(),
        ]);
    }

    /**
     * @dataProvider fixtureProvider
     */
    public function testDetectsCorrectAdapterForFixture(string $fixturePath, string $expectedBrokerId): void
    {
        $content = file_get_contents($fixturePath);
        self::assertNotFalse($content);

        $filename = basename($fixturePath);
        $adapter = $this->registry->detect($content, $filename);

        self::assertSame(
            $expectedBrokerId,
            $adapter->brokerId()->toString(),
            sprintf('Expected adapter "%s" for fixture "%s", got "%s"', $expectedBrokerId, $filename, $adapter->brokerId()->toString()),
        );
    }

    /**
     * @dataProvider fixtureProvider
     */
    public function testParsesFixtureSuccessfully(string $fixturePath, string $expectedBrokerId): void
    {
        $content = file_get_contents($fixturePath);
        self::assertNotFalse($content);

        $adapter = $this->registry->detect($content, basename($fixturePath));
        $result = $adapter->parse($content);

        self::assertSame(
            $expectedBrokerId,
            $result->metadata->broker->toString(),
        );

        // All sample fixtures should produce at least one transaction
        self::assertNotEmpty(
            $result->transactions,
            sprintf('Fixture "%s" produced zero transactions', basename($fixturePath)),
        );
    }

    public function testAllSupportedBrokersHaveFixtures(): void
    {
        $supportedBrokers = $this->registry->supportedBrokers();
        $fixturedBrokers = array_column(iterator_to_array($this->fixtureProvider()), 1);

        foreach ($supportedBrokers as $brokerId) {
            self::assertContains(
                $brokerId,
                $fixturedBrokers,
                sprintf('Broker "%s" is registered but has no fixture CSV for integration testing', $brokerId),
            );
        }
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function fixtureProvider(): \Generator
    {
        $fixtureDir = __DIR__ . '/../Fixtures';

        yield 'ibkr' => [$fixtureDir . '/ibkr_activity_sample.csv', 'ibkr'];
        yield 'degiro-transactions' => [$fixtureDir . '/degiro_transactions_sample.csv', 'degiro'];
        yield 'degiro-account-statement' => [$fixtureDir . '/degiro_account_statement_sample.csv', 'degiro'];
        yield 'revolut' => [$fixtureDir . '/revolut_stocks_sample.csv', 'revolut'];
        yield 'bossa' => [$fixtureDir . '/bossa_history_sample.csv', 'bossa'];
    }
}
