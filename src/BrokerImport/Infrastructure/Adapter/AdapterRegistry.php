<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter;

use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AdapterRegistry
{
    /**
     * @var iterable<BrokerAdapterInterface>
     */
    private iterable $adapters;

    /**
     * @param iterable<BrokerAdapterInterface> $adapters
     */
    public function __construct(
        #[AutowireIterator(BrokerAdapterInterface::class)]
        iterable $adapters,
    ) {
        $this->adapters = $adapters;
    }

    /**
     * Auto-detect: tries each adapter in order.
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
     * @return list<string> broker IDs
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
