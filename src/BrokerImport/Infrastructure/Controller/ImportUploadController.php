<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\BrokerImport\Application\DTO\FileValidationError;
use App\BrokerImport\Application\DTO\ImportResult;
use App\BrokerImport\Application\DTO\ParseMetadata;
use App\BrokerImport\Application\DTO\ParseResult;
use App\BrokerImport\Application\Port\BrokerAdapterRequestPort;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\BrokerImport\Domain\Exception\BrokerFileMismatchException;
use App\BrokerImport\Domain\Exception\ImportRowLimitExceededException;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use App\BrokerImport\Infrastructure\Mailer\ImportSuccessMailer;
use App\BrokerImport\Infrastructure\Validation\UploadedFileValidator;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportUploadController extends AbstractController
{
    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
        private readonly RateLimiterFactory $importUploadLimiter,
        private readonly UploadedFileValidator $fileValidator,
        private readonly ImportOrchestrationService $importOrchestration,
        private readonly BrokerAdapterRequestPort $adapterRequestService,
        private readonly ImportSuccessMailer $importSuccessMailer,
    ) {
    }

    #[Route('/import/upload', name: 'import_upload', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('import_upload', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Nieprawidlowy token CSRF. Sprobuj ponownie.');

            return $this->redirectToRoute('import_index');
        }

        if (! $this->consumeRateLimit($request)) {
            $this->addFlash('error', 'Zbyt wiele importow. Sprobuj ponownie za kilka minut.');

            return $this->redirectToRoute('import_index');
        }

        $uploadedFiles = $this->resolveUploadedFiles($request);

        if ($uploadedFiles === []) {
            $this->addFlash('error', 'Nie wybrano zadnego pliku.');

            return $this->redirectToRoute('import_index');
        }

        $userId = $this->resolveUserId();
        $forceReimport = $request->request->getBoolean('force_reimport');
        $brokerId = $request->request->getString('broker_id');

        $processedResults = [];
        $skippedDuplicates = 0;

        foreach ($uploadedFiles as $file) {
            $validationError = $this->fileValidator->validate($file);

            if ($validationError !== null) {
                $this->addFlash('error', $validationError->value);
                continue;
            }

            $contentOrError = $this->fileValidator->readContent($file);

            if ($contentOrError instanceof FileValidationError) {
                $this->addFlash('error', $contentOrError->value);
                continue;
            }

            $fileContent = $contentOrError;

            if (! $forceReimport && $this->importOrchestration->wasAlreadyImported($userId, $fileContent)) {
                $skippedDuplicates++;
                continue;
            }

            $originalFilename = $this->sanitizeFilename($file->getClientOriginalName());

            try {
                $processedResults[] = $this->importFile($userId, $fileContent, $originalFilename, $brokerId);
            } catch (BrokerFileMismatchException) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Plik "%s" nie pasuje do wybranego brokera. Sprawdz wybor lub uzyj auto-detect.',
                        basename($file->getClientOriginalName()),
                    ),
                );
            } catch (ImportRowLimitExceededException $e) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Plik "%s" zawiera %d transakcji, co przekracza limit %d wierszy dla wersji beta.',
                        basename($file->getClientOriginalName()),
                        $e->rowCount,
                        $e->limit,
                    ),
                );
            } catch (UnsupportedBrokerFormatException) {
                $this->submitForAdapterReview($userId, $fileContent, $originalFilename);
                $this->addFlash('format_error_broker', $brokerId);
            }
        }

        if ($processedResults === []) {
            if ($skippedDuplicates > 0) {
                $this->addFlash(
                    'warning',
                    $skippedDuplicates === 1
                        ? 'Ten plik zostal juz zaimportowany. Wgraj inny plik lub uzyj opcji "Wymusz ponowny import".'
                        : sprintf('Wszystkie %d pliki zostaly juz zaimportowane.', $skippedDuplicates),
                );
            }

            return $this->redirectToRoute('import_index');
        }

        if ($skippedDuplicates > 0) {
            $this->addFlash(
                'warning',
                sprintf('Pominieto %d duplikat%s (plik zostal juz wczesniej zaimportowany).', $skippedDuplicates, $skippedDuplicates === 1 ? '' : 'ow'),
            );
        }

        $lastResult = end($processedResults);
        $totalImported = (int) array_sum(array_map(static fn (ImportResult $r): int => $r->importedCount, $processedResults));
        $fifoWarnings = array_merge(...array_map(static fn (ImportResult $r): array => $r->fifoWarnings, $processedResults));

        foreach ($fifoWarnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        $this->addFlash(
            'success',
            sprintf(
                'Zaimportowano %d transakcji z %s. Lacznie: %d transakcji z %d %s.',
                $totalImported,
                $lastResult->brokerDisplayName,
                $lastResult->totalTransactionCount,
                $lastResult->brokerCount,
                $lastResult->brokerCount === 1 ? 'brokera' : 'brokerow',
            ),
        );

        $this->sendImportSuccessEmail($lastResult, $totalImported);

        $mergedParseResult = $this->mergeParseResults(
            array_map(static fn (ImportResult $r): ParseResult => $r->parseResult, $processedResults),
        );

        return $this->render('import/results.html.twig', [
            'result' => $mergedParseResult,
            'brokerId' => $lastResult->brokerId,
            'brokerDisplayName' => $lastResult->brokerDisplayName,
        ]);
    }

    /**
     * @return list<UploadedFile>
     */
    private function resolveUploadedFiles(Request $request): array
    {
        $raw = $request->files->get('broker_file');

        if ($raw instanceof UploadedFile) {
            return [$raw];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, static fn ($f): bool => $f instanceof UploadedFile));
        }

        // Legacy single-file field name
        $legacy = $request->files->get('csv_file');

        if ($legacy instanceof UploadedFile) {
            return [$legacy];
        }

        return [];
    }

    /**
     * @param list<ParseResult> $results
     */
    private function mergeParseResults(array $results): ParseResult
    {
        $transactions = [];
        $errors = [];
        $warnings = [];
        $dateFrom = null;
        $dateTo = null;
        $broker = null;
        $sectionsFound = [];
        $totalTx = 0;
        $totalErrors = 0;

        foreach ($results as $r) {
            $transactions = array_merge($transactions, $r->transactions);
            $errors = array_merge($errors, $r->errors);
            $warnings = array_merge($warnings, $r->warnings);
            $totalTx += $r->metadata->totalTransactions;
            $totalErrors += $r->metadata->totalErrors;

            if ($broker === null) {
                $broker = $r->metadata->broker;
            }

            if ($r->metadata->dateFrom !== null) {
                if ($dateFrom === null || $r->metadata->dateFrom < $dateFrom) {
                    $dateFrom = $r->metadata->dateFrom;
                }
            }

            if ($r->metadata->dateTo !== null) {
                if ($dateTo === null || $r->metadata->dateTo > $dateTo) {
                    $dateTo = $r->metadata->dateTo;
                }
            }

            foreach ($r->metadata->sectionsFound as $section) {
                if (! \in_array($section, $sectionsFound, true)) {
                    $sectionsFound[] = $section;
                }
            }
        }

        return new ParseResult(
            $transactions,
            $errors,
            $warnings,
            new ParseMetadata($broker, $totalTx, $totalErrors, $dateFrom, $dateTo, $sectionsFound),
        );
    }

    private function importFile(
        UserId $userId,
        string $fileContent,
        string $sanitizedFilename,
        string $brokerId,
    ): ImportResult {
        if ($brokerId !== '' && $brokerId !== 'auto') {
            $adapter = $this->adapterRegistry->findByAdapterKey($brokerId);

            return $this->importOrchestration->importWithAdapter($userId, $fileContent, $sanitizedFilename, $adapter);
        }

        return $this->importOrchestration->import($userId, $fileContent, $sanitizedFilename);
    }

    private function submitForAdapterReview(UserId $userId, string $fileContent, string $filename): void
    {
        try {
            $this->adapterRequestService->submit($userId, $filename, $fileContent);
            $this->addFlash(
                'warning',
                'Nie rozpoznalismy formatu pliku. Przeslalismy go do weryfikacji — dodamy obsluge tego brokera jesli to mozliwe.',
            );
        } catch (\Throwable) {
            $this->addFlash('error', 'Nie rozpoznano formatu pliku. Wspierane brokery: Interactive Brokers, Degiro, Revolut, Bossa, XTB.');
        }
    }

    private function consumeRateLimit(Request $request): bool
    {
        /** @var SecurityUser|null $user */
        $user = $this->getUser();
        $key = $user !== null ? $user->id() : (string) $request->getClientIp();

        return $this->importUploadLimiter->create($key)->consume()->isAccepted();
    }

    private function sendImportSuccessEmail(ImportResult $result, int $totalImported): void
    {
        /** @var SecurityUser|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return;
        }

        try {
            $this->importSuccessMailer->sendImportSuccess(
                $user->email(),
                $totalImported,
                $result->brokerDisplayName,
                $result->totalTransactionCount,
                $result->brokerCount,
            );
        } catch (\Throwable) {
            // Email failure must not block the user — the import itself succeeded.
        }
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
     * Sanitizes filename: strips path traversal, replaces unsafe chars, prepends UUID.
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? 'file.csv';
        $filename = ltrim($filename, '.');

        if ($filename === '') {
            $filename = 'broker_file';
        }

        return sprintf('%s_%s', Uuid::v4()->toRfc4122(), $filename);
    }
}
