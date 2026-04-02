<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Controller;

use App\BrokerImport\Domain\Exception\UnsupportedBrokerFormatException;
use App\BrokerImport\Infrastructure\Adapter\AdapterRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ImportController extends AbstractController
{
    private const int MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024; // 50 MB

    private const array ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    public function __construct(
        private readonly AdapterRegistry $adapterRegistry,
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
            $this->addFlash('error', 'Nieprawidłowy token CSRF. Spróbuj ponownie.');

            return $this->redirectToRoute('import_index');
        }

        $file = $request->files->get('csv_file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            $this->addFlash('error', 'Nie przesłano poprawnego pliku.');

            return $this->redirectToRoute('import_index');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            $this->addFlash('error', 'Plik jest zbyt duży. Maksymalny rozmiar to 50 MB.');

            return $this->redirectToRoute('import_index');
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            $this->addFlash('error', 'Nieprawidłowy format pliku. Dozwolone: CSV.');

            return $this->redirectToRoute('import_index');
        }

        $originalFilename = $this->sanitizeFilename($file->getClientOriginalName());
        $csvContent = file_get_contents($file->getPathname());

        if ($csvContent === false || $csvContent === '') {
            $this->addFlash('error', 'Nie można odczytać zawartości pliku.');

            return $this->redirectToRoute('import_index');
        }

        try {
            $adapter = $this->adapterRegistry->detect($csvContent, $originalFilename);
        } catch (UnsupportedBrokerFormatException) {
            $this->addFlash('error', 'Nie rozpoznano formatu pliku. Upewnij się, że plik pochodzi ze wspieranego brokera.');

            return $this->redirectToRoute('import_index');
        }

        $result = $adapter->parse($csvContent);

        return $this->render('import/results.html.twig', [
            'result' => $result,
            'filename' => $originalFilename,
            'brokerId' => $adapter->brokerId()->toString(),
        ]);
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
