<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\BrokerImport\Application\DTO\FileValidationError;
use App\BrokerImport\Application\DTO\ImportResult;
use App\BrokerImport\Application\Service\ImportOrchestrationService;
use App\BrokerImport\Domain\Exception\BrokerFileMismatchException;
use App\BrokerImport\Domain\Exception\ImportRowLimitExceededException;
use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
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

        /** @var UploadedFile|null $file */
        $file = $request->files->get('broker_file') ?? $request->files->get('csv_file');

        $validationError = $this->fileValidator->validate($file);

        if ($validationError !== null) {
            $this->addFlash('error', $validationError->value);

            return $this->redirectToRoute('import_index');
        }

        assert($file instanceof UploadedFile);
        $contentOrError = $this->fileValidator->readContent($file);

        if ($contentOrError instanceof FileValidationError) {
            $this->addFlash('error', $contentOrError->value);

            return $this->redirectToRoute('import_index');
        }

        $fileContent = $contentOrError;
        $userId = $this->resolveUserId();
        $forceReimport = $request->request->getBoolean('force_reimport');

        if (! $forceReimport && $this->importOrchestration->wasAlreadyImported($userId, $fileContent)) {
            $this->addFlash('warning', 'Ten plik zostal juz zaimportowany. Aby zaimportowac ponownie, zaznacz opcje "Wymusz ponowny import".');

            return $this->redirectToRoute('import_index');
        }

        $originalFilename = $this->sanitizeFilename($file->getClientOriginalName());
        $brokerId = $request->request->getString('broker_id');

        try {
            $result = $this->importFile($userId, $fileContent, $originalFilename, $brokerId);
        } catch (BrokerFileMismatchException) {
            $this->addFlash(
                'error',
                'Ten plik nie wyglada na raport z wybranego brokera. Sprawdz czy wybrales wlasciwego brokera lub wybierz "Auto-detect".',
            );

            return $this->redirectToRoute('import_index');
        } catch (ImportRowLimitExceededException $e) {
            $this->addFlash(
                'error',
                sprintf(
                    'Twoj plik zawiera %d transakcji, co przekracza limit %d wierszy dla wersji beta. '
                    . 'Napisz do nas na hello@taxpilot.pl — obslugujemy wieksze portfele recznie.',
                    $e->rowCount,
                    $e->limit,
                ),
            );

            return $this->redirectToRoute('import_index');
        } catch (UnsupportedBrokerFormatException) {
            $this->addFlash('error', 'Nie rozpoznano formatu pliku. Wspierane brokery: Interactive Brokers, Degiro, Revolut, Bossa, XTB. Upewnij sie, ze wgrywasz raport brokera, a nie podsumowanie konta.');
            $this->addFlash('format_error_broker', $brokerId);

            return $this->redirectToRoute('import_index');
        }

        foreach ($result->fifoWarnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        $this->addFlash(
            'success',
            sprintf(
                'Zaimportowano %d transakcji z %s. Lacznie: %d transakcji z %d %s.',
                $result->importedCount,
                $result->brokerDisplayName,
                $result->totalTransactionCount,
                $result->brokerCount,
                $result->brokerCount === 1 ? 'brokera' : 'brokerow',
            ),
        );

        return $this->render('import/results.html.twig', [
            'result' => $result->parseResult,
            'filename' => $originalFilename,
            'brokerId' => $result->brokerId,
            'brokerDisplayName' => $result->brokerDisplayName,
        ]);
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

    private function consumeRateLimit(Request $request): bool
    {
        $limiter = $this->importUploadLimiter->create((string) $request->getClientIp());

        return $limiter->consume()->isAccepted();
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
