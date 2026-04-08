<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\Bossa\BossaHistoryAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroAccountStatementAdapter;
use App\BrokerImport\Infrastructure\Adapter\Degiro\DegiroTransactionsAdapter;
use App\BrokerImport\Infrastructure\Adapter\IBKR\IBKRActivityAdapter;
use App\BrokerImport\Infrastructure\Adapter\Revolut\RevolutStocksAdapter;
use App\BrokerImport\Infrastructure\Adapter\XTB\XTBStatementAdapter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AdapterRegistry implements BrokerDetectorPort
{
    /**
     * Maps wizard-level adapter keys to adapter class names.
     *
     * Each adapter has a unique key used in the import wizard dropdown.
     * This is separate from BrokerId (which may be shared, e.g. both Degiro
     * adapters use 'degiro' as BrokerId for domain/storage purposes).
     *
     * @var array<string, class-string<BrokerAdapterInterface>>
     */
    private const array ADAPTER_KEY_MAP = [
        'ibkr' => IBKRActivityAdapter::class,
        'degiro_transactions' => DegiroTransactionsAdapter::class,
        'degiro_account' => DegiroAccountStatementAdapter::class,
        'revolut' => RevolutStocksAdapter::class,
        'bossa' => BossaHistoryAdapter::class,
        'xtb' => XTBStatementAdapter::class,
    ];

    /**
     * Human-readable labels for the wizard dropdown.
     *
     * @var array<string, string>
     */
    private const array ADAPTER_DISPLAY_NAMES = [
        'ibkr' => 'Interactive Brokers — Activity Statement (CSV)',
        'degiro_transactions' => 'Degiro — Transactions (CSV)',
        'degiro_account' => 'Degiro — Account Statement (CSV) [dywidendy]',
        'revolut' => 'Revolut — Stocks Statement (CSV)',
        'bossa' => 'Bossa — Historia transakcji (CSV)',
        'xtb' => 'XTB — Statement (XLSX)',
    ];

    /**
     * @var list<BrokerAdapterInterface> sorted by priority DESC (most specific first)
     */
    private array $adapters;

    /**
     * @param iterable<BrokerAdapterInterface> $adapters
     */
    public function __construct(
        #[AutowireIterator(BrokerAdapterInterface::class)]
        iterable $adapters,
    ) {
        $sorted = $adapters instanceof \Traversable
            ? iterator_to_array($adapters, false)
            : array_values($adapters);

        usort($sorted, static fn (BrokerAdapterInterface $a, BrokerAdapterInterface $b): int => $b->priority() <=> $a->priority());

        $this->adapters = $sorted;
    }

    /**
     * Auto-detect: tries each adapter in priority order (highest first).
     * First supports() = true wins.
     */
    public function detect(string $fileContent, string $filename): BrokerAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($fileContent, $filename)) {
                return $adapter;
            }
        }

        throw new UnsupportedBrokerFormatException($filename);
    }

    /**
     * Find a specific adapter by its wizard-level adapter key.
     *
     * @throws \InvalidArgumentException when the key is not registered
     */
    public function findByAdapterKey(string $adapterKey): BrokerAdapterInterface
    {
        $targetClass = self::ADAPTER_KEY_MAP[$adapterKey] ?? null;

        if ($targetClass === null) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown adapter key "%s". Valid keys: %s',
                $adapterKey,
                implode(', ', array_keys(self::ADAPTER_KEY_MAP)),
            ));
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof $targetClass) {
                return $adapter;
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Adapter for key "%s" (%s) is not registered in the container.',
            $adapterKey,
            $targetClass,
        ));
    }

    /**
     * Returns adapter keys with human-readable display names for the wizard dropdown.
     *
     * Only includes adapters that are actually registered in the container.
     *
     * @return array<string, string> adapter key => display name
     */
    public function adapterChoices(): array
    {
        $registeredClasses = [];

        foreach ($this->adapters as $adapter) {
            $registeredClasses[$adapter::class] = true;
        }

        $choices = [];

        foreach (self::ADAPTER_KEY_MAP as $key => $class) {
            if (isset($registeredClasses[$class])) {
                $choices[$key] = self::ADAPTER_DISPLAY_NAMES[$key];
            }
        }

        return $choices;
    }

    /**
     * @return list<string> broker IDs (ordered by priority)
     */
    public function supportedBrokers(): array
    {
        $brokers = [];

        foreach ($this->adapters as $adapter) {
            $brokers[] = $adapter->brokerId()->toString();
        }

        return $brokers;
    }
}
