<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * P2-110 — HTTP-level file upload security.
 *
 * Verifies that the import endpoint rejects dangerous files before any
 * processing occurs.  The UploadedFileValidator checks:
 *  - extension: must be .csv
 *  - MIME type: must be text/csv, text/plain, or application/csv
 *
 * Each test case expects a redirect back to /import with an error flash —
 * never a 500 or a 200 with the file silently accepted.
 *
 * @group security
 */
final class FileUploadSecurityTest extends WebTestCase
{
    private const string USER_ID = '00000000-0000-0000-0008-000000000001';

    private const string USER_EMAIL = 'file-upload-security@example.com';

    /**
     * Uploading shell.php must be rejected (wrong extension).
     */
    public function testPhpFileIsRejected(): void
    {
        [$client, $csrfToken] = $this->createAuthenticatedClientWithCsrf();

        $tmpFile = $this->createTempFile('<?php system($_GET[\'cmd\']); ?>', 'shell.php');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'shell.php',
            'text/plain',
            null,
            true, // test mode — skip is_uploaded_file() check
        );

        $client->request(
            'POST',
            '/import/upload',
            ['_token' => $csrfToken],
            ['csv_file' => $uploadedFile],
        );

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();
        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(
            0,
            $errorFlash->count(),
            'Expected an error flash message when uploading shell.php.',
        );

        @unlink($tmpFile);
    }

    /**
     * Uploading .htaccess must be rejected (wrong extension).
     */
    public function testHtaccessFileIsRejected(): void
    {
        [$client, $csrfToken] = $this->createAuthenticatedClientWithCsrf();

        $tmpFile = $this->createTempFile('Options +ExecCGI', '.htaccess');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            '.htaccess',
            'text/plain',
            null,
            true,
        );

        $client->request(
            'POST',
            '/import/upload',
            ['_token' => $csrfToken],
            ['csv_file' => $uploadedFile],
        );

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();
        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(
            0,
            $errorFlash->count(),
            'Expected an error flash message when uploading .htaccess.',
        );

        @unlink($tmpFile);
    }

    /**
     * A file with .csv extension but MIME type application/x-php must be rejected.
     */
    public function testCsvExtensionWithPhpMimeTypeIsRejected(): void
    {
        [$client, $csrfToken] = $this->createAuthenticatedClientWithCsrf();

        $tmpFile = $this->createTempFile('<?php echo "pwned"; ?>', 'payload.csv');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'payload.csv',
            'application/x-php',
            null,
            true,
        );

        $client->request(
            'POST',
            '/import/upload',
            ['_token' => $csrfToken],
            ['csv_file' => $uploadedFile],
        );

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();
        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(
            0,
            $errorFlash->count(),
            'Expected an error flash message for .csv file with application/x-php MIME type.',
        );

        @unlink($tmpFile);
    }

    /**
     * Creates an authenticated KernelBrowser with a valid CSRF token injected into the session.
     *
     * @return array{\Symfony\Bundle\FrameworkBundle\KernelBrowser, string}
     */
    private function createAuthenticatedClientWithCsrf(): array
    {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get(\Doctrine\DBAL\Connection::class);

        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            ['id' => self::USER_ID],
        );

        if ($exists === 0) {
            $connection->insert('users', [
                'id' => self::USER_ID,
                'email' => self::USER_EMAIL,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'referral_code' => 'FUPLOAD01',
                'bonus_transactions' => 0,
            ]);
        }

        $client->loginUser(new SecurityUser(self::USER_ID, self::USER_EMAIL));

        // Warm up the session via GET /import
        $client->request('GET', '/import');

        $knownToken = 'test-csrf-import-upload-security';
        $session = $client->getRequest()->getSession();
        $session->set('_csrf/import_upload', $knownToken);
        $session->save();

        return [$client, $knownToken];
    }

    /**
     * Creates a temporary file with the given content and returns its path.
     * The filename hint is used only to match upload metadata — the actual tmp file
     * may have a different name on disk.
     */
    private function createTempFile(string $content, string $_filenameHint): string
    {
        $path = tempnam(sys_get_temp_dir(), 'taxpilot_sec_');
        file_put_contents($path, $content);

        return $path;
    }
}
