<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Port;

use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;

/**
 * Port for detecting broker import files.
 *
 * The Application layer uses this interface; Infrastructure provides
 * the AdapterRegistry implementation that tries adapters in priority order.
 */
interface BrokerDetectorPort
{
    /**
     * Detect the broker and return the appropriate adapter for parsing.
     *
     * @throws UnsupportedBrokerFormatException when no adapter recognizes the file
     */
    public function detect(string $fileContent, string $filename): BrokerAdapterInterface;
}
