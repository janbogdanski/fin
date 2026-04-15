<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport\Application;

use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterInterface;
use App\BrokerImport\Application\Port\BrokerDetectorPort;
use App\BrokerImport\Application\Port\DividendProcessorPort;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\Shared\Domain\Port\AuditLogPort;
use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Service\ImportToLedgerService;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use App\Tests\Factory\NormalizedTransactionMother;
use App\Tests\InMemory\FixedExchangeRateProvider;
use App\Tests\InMemory\InMemoryImportStorageAdapter;
use App\Tests\InMemory\InMemoryTaxPositionLedgerRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ImportLifecycleProcessTest extends TestCase
{
    private InMemoryImportStorageAdapter $importStorage;

    private InMemoryTaxPositionLedgerRepository $ledgerRepository;

    private ImportOrchestrationService $service;

    private ImportToLedgerService $fifoProcessor;

    private UserId $userId;

    protected function setUp(): void
    {
        $this->importStorage = new InMemoryImportStorageAdapter();
        $this->ledgerRepository = new InMemoryTaxPositionLedgerRepository();
        $this->fifoProcessor = new ImportToLedgerService(
            new CurrencyConverter(),
            new FixedExchangeRateProvider([
                'USD' => '4.0000',
            ]),
            $this->ledgerRepository,
            new NullLogger(),
        );

        $this->service = new ImportOrchestrationService(
            new class() implements BrokerDetectorPort {
                public function detect(string $fileContent, string $filename): BrokerAdapterInterface
                {
                    throw new \LogicException('Auto-detection is not used in this process test.');
                }
            },
            $this->importStorage,
            $this->fifoProcessor,
            new class() implements DividendProcessorPort {
                public function process(array $transactions, UserId $userId, TaxYear $taxYear): array
                {
                    return [];
                }
            },
            new class() implements AuditLogPort {
                public function log(string $eventType, ?string $userId, array $context = [], ?string $ipAddress = null): void
                {
                }
            },
            new NullLogger(),
        );

        $this->userId = UserId::generate();
    }

    public function testTopUpImportClosesPreviousYearBuyWithoutFullReimport(): void
    {
        $buy2024 = NormalizedTransactionMother::buyAAPL(
            date: new \DateTimeImmutable('2024-06-15'),
        );
        $sell2025 = NormalizedTransactionMother::sellAAPL(
            date: new \DateTimeImmutable('2025-02-10'),
        );

        $this->service->importWithAdapter(
            $this->userId,
            'buy-2024',
            'xtb-2024.xlsx',
            new FakeBrokerAdapter('xtb', [$buy2024]),
        );

        $result = $this->service->importWithAdapter(
            $this->userId,
            'sell-2025',
            'xtb-2025.xlsx',
            new FakeBrokerAdapter('xtb', [$sell2025]),
        );

        $persisted = $this->ledgerRepository->closedPositionsForUserAndYear($this->userId, TaxYear::of(2025));

        self::assertSame([], $result->fifoWarnings);
        self::assertSame(2, $this->importStorage->getTotalTransactionCount($this->userId));
        self::assertCount(1, $persisted);
        self::assertSame('2024', $persisted[0]->buyDate->format('Y'));
        self::assertSame('2025', $persisted[0]->sellDate->format('Y'));

        $ledger = $this->ledgerRepository->findByUserAndISIN(
            $this->userId,
            ISIN::fromString('US0378331005'),
        );

        self::assertNotNull($ledger);
        self::assertCount(0, $ledger->openPositions());
    }

    public function testReplayingFullHistoryDoesNotDuplicatePersistedClosedPositions(): void
    {
        $buy2024 = NormalizedTransactionMother::buyAAPL(
            date: new \DateTimeImmutable('2024-06-15'),
        );
        $sell2025 = NormalizedTransactionMother::sellAAPL(
            date: new \DateTimeImmutable('2025-02-10'),
        );

        $transactions = [$buy2024, $sell2025];

        $this->fifoProcessor->process(
            $transactions,
            $this->userId,
            TaxYear::of(2025),
            persist: true,
        );
        $this->fifoProcessor->process(
            $transactions,
            $this->userId,
            TaxYear::of(2025),
            persist: true,
        );

        $persisted = $this->ledgerRepository->closedPositionsForUserAndYear($this->userId, TaxYear::of(2025));
        $ledger = $this->ledgerRepository->findByUserAndISIN(
            $this->userId,
            ISIN::fromString('US0378331005'),
        );

        self::assertCount(1, $persisted);
        self::assertNotNull($ledger);
        self::assertCount(0, $ledger->openPositions());
    }
}

final readonly class FakeBrokerAdapter implements BrokerAdapterInterface
{
    /**
     * @param list<\App\BrokerImport\Application\DTO\NormalizedTransaction> $transactions
     */
    public function __construct(
        private string $brokerId,
        private array $transactions,
    ) {
    }

    public function brokerId(): BrokerId
    {
        return BrokerId::of($this->brokerId);
    }

    public function supports(string $content, string $filename): bool
    {
        return true;
    }

    public function parse(string $fileContent, string $filename = ''): ParseResult
    {
        $dateFrom = null;
        $dateTo = null;

        foreach ($this->transactions as $transaction) {
            $dateFrom ??= $transaction->date;
            $dateTo ??= $transaction->date;

            if ($transaction->date < $dateFrom) {
                $dateFrom = $transaction->date;
            }

            if ($transaction->date > $dateTo) {
                $dateTo = $transaction->date;
            }
        }

        return new ParseResult(
            transactions: $this->transactions,
            errors: [],
            warnings: [],
            metadata: new ParseMetadata(
                broker: $this->brokerId(),
                totalTransactions: count($this->transactions),
                totalErrors: 0,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                sectionsFound: ['process-test'],
            ),
        );
    }

    public function priority(): int
    {
        return 100;
    }
}
