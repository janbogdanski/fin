<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\DTO;

/**
 * Result of the import orchestration process.
 *
 * Encapsulates everything the controller needs to render the result page:
 * parsed transactions, broker info, user totals, and any FIFO warnings.
 */
final readonly class ImportResult
{
    /**
     * @param list<string> $fifoWarnings warnings from FIFO matching (e.g. sell without matching buy)
     */
    public function __construct(
        public ParseResult $parseResult,
        public string $brokerId,
        public string $brokerDisplayName,
        public int $importedCount,
        public int $totalTransactionCount,
        public int $brokerCount,
        public array $fifoWarnings = [],
    ) {
    }
}
