<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\BrokerImport\Infrastructure\Controller\ImportController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * P1-015: Verifies duplicate CSV detection via content hash in session.
 */
final class ImportControllerDedupTest extends KernelTestCase
{
    private const string SAMPLE_CSV = "Date,Symbol,Type,Quantity,Price,Currency\n2025-01-15,AAPL,BUY,10,185.50,USD\n";

    public function testSecondUploadOfSameFileShowsWarning(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $session = new Session(new MockArraySessionStorage());
        $this->pushSessionToRequestStack($session);

        /** @var CsrfTokenManagerInterface $csrfManager */
        $csrfManager = $container->get('security.csrf.token_manager');
        $validToken = $csrfManager->getToken('import_upload')->getValue();

        /** @var ImportController $controller */
        $controller = $container->get(ImportController::class);
        $controller->setContainer($container);

        // First upload — the adapter will likely not recognize the format, but
        // the dedup hash is checked BEFORE adapter detection, so we need a
        // recognizable file. For dedup testing, any file that passes validation
        // and gets to the hash check is sufficient. The first upload will fail
        // at adapter detection (unrecognized format) and NOT store the hash.
        // So we need to test the scenario where hash IS stored (successful parse)
        // and then re-uploaded.

        // Simpler approach: pre-seed the session with a hash and verify
        // the second upload is blocked.
        $contentHash = hash('sha256', self::SAMPLE_CSV);
        $session->set('_imported_csv_hashes', [$contentHash]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpFile, self::SAMPLE_CSV);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.csv',
            'text/csv',
            null,
            true,
        );

        $request = Request::create('/import/upload', 'POST', [
            '_token' => $validToken,
        ], [], [
            'csv_file' => $uploadedFile,
        ]);
        $request->setSession($session);

        $response = $controller->upload($request);

        self::assertSame(302, $response->getStatusCode());

        $flashes = $session->getFlashBag()->peekAll();
        $allWarnings = implode(' ', $flashes['warning'] ?? []);
        self::assertStringContainsString('zaimportowany', $allWarnings);

        @unlink($tmpFile);
    }

    public function testForceReimportBypassesDedupCheck(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $session = new Session(new MockArraySessionStorage());
        $this->pushSessionToRequestStack($session);

        /** @var CsrfTokenManagerInterface $csrfManager */
        $csrfManager = $container->get('security.csrf.token_manager');
        $validToken = $csrfManager->getToken('import_upload')->getValue();

        /** @var ImportController $controller */
        $controller = $container->get(ImportController::class);
        $controller->setContainer($container);

        // Pre-seed hash
        $contentHash = hash('sha256', self::SAMPLE_CSV);
        $session->set('_imported_csv_hashes', [$contentHash]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpFile, self::SAMPLE_CSV);

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.csv',
            'text/csv',
            null,
            true,
        );

        $request = Request::create('/import/upload', 'POST', [
            '_token' => $validToken,
            'force_reimport' => '1',
        ], [], [
            'csv_file' => $uploadedFile,
        ]);
        $request->setSession($session);

        $response = $controller->upload($request);

        // Should NOT redirect with dedup warning — it bypasses the check.
        // It will proceed to adapter detection and likely fail there (unrecognized format),
        // but the important thing is it doesn't show the dedup warning.
        $flashes = $session->getFlashBag()->peekAll();
        $allWarnings = implode(' ', $flashes['warning'] ?? []);
        self::assertStringNotContainsString('zaimportowany', $allWarnings);

        @unlink($tmpFile);
    }

    private function pushSessionToRequestStack(Session $session): void
    {
        $container = self::getContainer();

        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);

        $mainRequest = Request::create('/');
        $mainRequest->setSession($session);
        $requestStack->push($mainRequest);
    }
}
