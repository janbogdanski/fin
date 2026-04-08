<?php

declare(strict_types=1);

namespace App\BrokerImport\Application\Service;

use App\BrokerImport\Application\DTO\ImportResult;
use App\BrokerImport\Application\DTO\NormalizedTransaction;
use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\BrokerImport\Application\Port\FifoProcessorPort;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Domain\Exception\BrokerFileMismatchException;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\ValueObject\TaxYear;

/**
 * Orchestrates the full broker-file import pipeline:
 * dedup check -> broker detection -> parsing -> persistence -> FIFO matching -> dividend processing.
 *
 * This is an Application Service -- it coordinates domain and infrastructure services
 * but contains no business rules itself. The controller delegates here after
 * handling HTTP concerns (CSRF, rate limiting, file validation).
 */
final readonly class ImportOrchestrationService
{
    /**
     * Human-readable broker names for UI display.
     * Keys match BrokerId::toString() values from adapters.
     */
    private const array BROKER_DISPLAY_NAMES = [
        'ibkr' => 'Interactive Brokers (Activity Statement)',
        'degiro' => 'Degiro',
        'revolut' => 'Revolut (Stocks Statement)',
        'bossa' => 'Bossa (Historia transakcji)',
        'xtb' => 'XTB (Statement)',
        'degiro_transactions' => 'Degiro (Transactions)',
        'degiro_account' => 'Degiro (Account Statement)',
    ];

    public function __construct(
        private BrokerDetectorPort $brokerDetector,
        private ImportStoragePort $importStorage,
        private FifoProcessorPort $fifoProcessor,
        private DividendProcessorPort $dividendProcessor,
    ) {
    }

    /**
     * Check if this file was already imported by this user.
     */
    public function wasAlreadyImported(UserId $userId, string $fileContent): bool
    {
        $contentHash = hash('sha256', $fileContent);

        return $this->importStorage->wasAlreadyImported($userId, $contentHash);
    }

    /**
     * Run the full import pipeline with a specific adapter (no auto-detection).
     *
     * Used by the wizard when the user explicitly selects a broker.
     * If the adapter's supports() returns false, the file does not match the selected broker.
     *
     * @throws BrokerFileMismatchException when the file does not match the selected adapter
     */
    public function importWithAdapter(
        UserId $userId,
        string $fileContent,
        string $sanitizedFilename,
        BrokerAdapterInterface $adapter,
    ): ImportResult {
        if (! $adapter->supports($fileContent, $sanitizedFilename)) {
            throw new BrokerFileMismatchException($adapter->brokerId()->toString(), $sanitizedFilename);
        }

        return $this->executeImportPipeline($userId, $fileContent, $sanitizedFilename, $adapter);
    }

    /**
     * Run the full import pipeline with auto-detection.
     *
     * @throws UnsupportedBrokerFormatException when no adapter recognizes the file
     */
    public function import(UserId $userId, string $fileContent, string $sanitizedFilename): ImportResult
    {
        $adapter = $this->brokerDetector->detect($fileContent, $sanitizedFilename);

        return $this->executeImportPipeline($userId, $fileContent, $sanitizedFilename, $adapter);
    }

    private function executeImportPipeline(
        UserId $userId,
        string $fileContent,
        string $sanitizedFilename,
        BrokerAdapterInterface $adapter,
    ): ImportResult {
        $contentHash = hash('sha256', $fileContent);

        $parseResult = $adapter->parse($fileContent, $sanitizedFilename);

        $brokerIdVO = $adapter->brokerId();
        $brokerId = $brokerIdVO->toString();
        $brokerDisplayName = self::BROKER_DISPLAY_NAMES[$brokerId] ?? strtoupper($brokerId);

        $fifoWarnings = [];

        if ($parseResult->transactions !== []) {
            $this->importStorage->store($userId, $brokerIdVO, $parseResult->transactions, $contentHash);

            $allTransactions = $this->importStorage->getAllTransactions($userId);
            $fifoWarnings = $this->processFifo($allTransactions, $userId);
            $this->processDividends($allTransactions, $userId);
        }

        return new ImportResult(
            parseResult: $parseResult,
            brokerId: $brokerId,
            brokerDisplayName: $brokerDisplayName,
            importedCount: count($parseResult->transactions),
            totalTransactionCount: $this->importStorage->getTotalTransactionCount($userId),
            brokerCount: $this->importStorage->getBrokerCount($userId),
            fifoWarnings: $fifoWarnings,
        );
    }

    /**
     * @param list<NormalizedTransaction> $allTransactions
     * @return list<string> FIFO warnings/errors
     */
    private function processFifo(array $allTransactions, UserId $userId): array
    {
        $warnings = [];
        $sellYears = $this->extractYearsByType($allTransactions, TransactionType::SELL);

        foreach ($sellYears as $year) {
            $fifoResult = $this->fifoProcessor->process(
                $allTransactions,
                $userId,
                TaxYear::of($year),
                persist: true,
            );

            foreach ($fifoResult->errors as $error) {
                $warnings[] = $error;
            }
        }

        return $warnings;
    }

    /**
     * @param list<NormalizedTransaction> $allTransactions
     */
    private function processDividends(array $allTransactions, UserId $userId): void
    {
        $dividendYears = $this->extractYearsByType($allTransactions, TransactionType::DIVIDEND);

        foreach ($dividendYears as $year) {
            $this->dividendProcessor->process(
                $allTransactions,
                $userId,
                TaxYear::of($year),
            );
        }
    }

    /**
     * Extract unique years that have transactions of the given type.
     *
     * @param list<NormalizedTransaction> $transactions
     * @return list<int>
     */
    private function extractYearsByType(array $transactions, TransactionType $type): array
    {
        $years = [];

        foreach ($transactions as $tx) {
            if ($tx->type === $type) {
                $years[(int) $tx->date->format('Y')] = true;
            }
        }

        return array_keys($years);
    }
}
