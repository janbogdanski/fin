<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AdapterRegistry implements BrokerDetectorPort
{
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
    public function detect(string $csvContent, string $filename): BrokerAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($csvContent, $filename)) {
                return $adapter;
            }
        }

        throw new UnsupportedBrokerFormatException($filename);
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
