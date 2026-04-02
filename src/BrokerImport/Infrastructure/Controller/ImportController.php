<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\BrokerImport\Application\DTO\TransactionType;
use App\BrokerImport\Application\Port\ImportStoragePort;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Application\Service\ImportDividendService;
use App\TaxCalc\Application\Service\ImportToLedgerService;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportController extends AbstractController
{
    /**
     * Pragmatic limit: real broker exports are typically 1-5 MB.
     * TODO: P2-028 -- implement streaming CSV parsing for large files.
     */
    private const int MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    private const array ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
    ];

    /**
     * Human-readable broker names for UI display.
     * Keys match BrokerId::toString() values from adapters.
     */
    private const array BROKER_DISPLAY_NAMES = [
        'ibkr' => 'Interactive Brokers (Activity Statement)',
        'degiro' => 'Degiro',
        'revolut' => 'Revolut (Stocks Statement)',
        'bossa' => 'Bossa (Historia transakcji)',
    ];

    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
        private readonly RateLimiterFactory $importUploadLimiter,
        private readonly ImportStoragePort $importStorage,
        private readonly ImportToLedgerService $importToLedger,
        private readonly ImportDividendService $importDividend,
    ) {
    }

    #[Route('/import', name: 'import_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('import/index.html.twig', [
            'supportedBrokers' => $this->adapterRegistry->supportedBrokers(),
        ]);
    }

    #[Route('/import/upload', name: 'import_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $token = $request->request->getString('_token');

        if (! $this->isCsrfTokenValid('import_upload', $token)) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('import_index');
        }

        $limiter = $this->importUploadLimiter->create((string) $request->getClientIp());

        if (! $limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Zbyt wiele importow. Sprobuj ponownie za kilka minut.');

            return $this->redirectToRoute('import_index');
        }

        $file = $request->files->get('csv_file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            $this->addFlash('error', 'Nie przeslano poprawnego pliku.');

            return $this->redirectToRoute('import_index');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            $this->addFlash('error', 'Plik jest zbyt duzy. Maksymalny rozmiar to 10 MB.');

            return $this->redirectToRoute('import_index');
        }

        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            $this->addFlash('error', 'Nieprawidlowe rozszerzenie pliku. Dozwolone: .csv');

            return $this->redirectToRoute('import_index');
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            $this->addFlash('error', 'Nieprawidlowy format pliku. Dozwolone: CSV.');

            return $this->redirectToRoute('import_index');
        }

        $originalFilename = $this->sanitizeFilename($file->getClientOriginalName());
        $csvContent = file_get_contents($file->getPathname());

        if ($csvContent === false || $csvContent === '') {
            $this->addFlash('error', 'Nie mozna odczytac zawartosci pliku.');

            return $this->redirectToRoute('import_index');
        }

        if (strlen($csvContent) > self::MAX_FILE_SIZE_BYTES) {
            $this->addFlash('error', 'Plik jest zbyt duzy. Maksymalny rozmiar to 10 MB.');

            return $this->redirectToRoute('import_index');
        }

        $userId = $this->resolveUserId();
        $contentHash = hash('sha256', $csvContent);
        $forceReimport = $request->request->getBoolean('force_reimport');

        if (! $forceReimport && $this->importStorage->wasAlreadyImported($userId, $contentHash)) {
            $this->addFlash('warning', 'Ten plik zostal juz zaimportowany. Aby zaimportowac ponownie, zaznacz opcje "Wymusz ponowny import".');

            return $this->redirectToRoute('import_index');
        }

        try {
            $adapter = $this->adapterRegistry->detect($csvContent, $originalFilename);
        } catch (UnsupportedBrokerFormatException) {
            $this->addFlash('error', 'Nie rozpoznano formatu pliku. Wspierane brokery: Interactive Brokers, Degiro, Revolut, Bossa. Upewnij sie, ze wgrywasz raport transakcji (nie podsumowanie konta).');

            return $this->redirectToRoute('import_index');
        }

        $result = $adapter->parse($csvContent);

        $brokerId = $adapter->brokerId()->toString();
        $brokerDisplayName = self::BROKER_DISPLAY_NAMES[$brokerId] ?? strtoupper($brokerId);

        if ($result->transactions !== []) {
            $this->importStorage->store($userId, $brokerId, $result->transactions, $contentHash);

            // Trigger FIFO matching: load all user transactions, process, persist
            $allTransactions = $this->importStorage->getAllTransactions($userId);
            $sellYears = $this->extractSellYears($allTransactions);

            foreach ($sellYears as $year) {
                $fifoResult = $this->importToLedger->process(
                    $allTransactions,
                    $userId,
                    TaxYear::of($year),
                    persist: true,
                );

                foreach ($fifoResult->errors as $error) {
                    $this->addFlash('warning', $error);
                }
            }

            // Trigger dividend tax calculation for all years with dividends
            $dividendYears = $this->extractDividendYears($allTransactions);

            foreach ($dividendYears as $year) {
                $this->importDividend->process(
                    $allTransactions,
                    $userId,
                    TaxYear::of($year),
                );
            }
        }

        $totalCount = $this->importStorage->getTotalTransactionCount($userId);
        $brokerCount = $this->importStorage->getBrokerCount($userId);

        $this->addFlash(
            'success',
            sprintf(
                'Zaimportowano %d transakcji z %s. Lacznie: %d transakcji z %d %s.',
                count($result->transactions),
                $brokerDisplayName,
                $totalCount,
                $brokerCount,
                $brokerCount === 1 ? 'brokera' : 'brokerow',
            ),
        );

        return $this->render('import/results.html.twig', [
            'result' => $result,
            'filename' => $originalFilename,
            'brokerId' => $brokerId,
            'brokerDisplayName' => $brokerDisplayName,
        ]);
    }

    private function resolveUserId(): UserId
    {
        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            throw new \RuntimeException('User must be authenticated to import transactions.');
        }

        return UserId::fromString($user->id());
    }

    /**
     * Extract unique years that have SELL transactions.
     *
     * @param list<\App\BrokerImport\Application\DTO\NormalizedTransaction> $transactions
     * @return list<int>
     */
    private function extractSellYears(array $transactions): array
    {
        $years = [];

        foreach ($transactions as $tx) {
            if ($tx->type === TransactionType::SELL) {
                $years[(int) $tx->date->format('Y')] = true;
            }
        }

        return array_keys($years);
    }

    /**
     * Extract unique years that have DIVIDEND transactions.
     *
     * @param list<\App\BrokerImport\Application\DTO\NormalizedTransaction> $transactions
     * @return list<int>
     */
    private function extractDividendYears(array $transactions): array
    {
        $years = [];

        foreach ($transactions as $tx) {
            if ($tx->type === TransactionType::DIVIDEND) {
                $years[(int) $tx->date->format('Y')] = true;
            }
        }

        return array_keys($years);
    }

    /**
     * Sanitizes filename: strips path traversal, replaces unsafe chars, prepends UUID.
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? 'file.csv';
        $filename = ltrim($filename, '.');

        if ($filename === '') {
            $filename = 'file.csv';
        }

        return sprintf('%s_%s', Uuid::v4()->toRfc4122(), $filename);
    }
}
