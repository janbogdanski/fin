<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * P2-022: Basic integration test for ImportController via HTTP client.
 *
 * Uses WebTestCase (browser-kit) to test the controller through the
 * full Symfony HTTP stack -- routing, middleware, response codes.
 */
final class ImportControllerWebTest extends AuthenticatedWebTestCase
{
    public function testGetImportReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/import');

        self::assertResponseIsSuccessful();
    }

    public function testPostUploadWithoutFileRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/import/upload');

        // Without a valid CSRF token, the controller redirects with an error flash
        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();

        // Base template renders flash errors in a div with bg-red-50 class
        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(0, $errorFlash->count(), 'Expected an error flash message after POST without file');
    }

    public function testPostUploadWithInvalidCsrfTokenRedirects(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/import/upload', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();

        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(0, $errorFlash->count(), 'Expected CSRF error flash');
        self::assertStringContainsString('CSRF', $errorFlash->text());
    }

    /**
     * Bug regression (Bug 3): form must carry data-turbo="false" so that
     * Turbo Drive does not intercept the multipart POST and lose the file.
     */
    public function testImportPageFormHasTurboDisabledAttribute(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/import');

        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action*="import"]');
        self::assertGreaterThan(0, $form->count(), 'Import form not found on /import page');
        self::assertSame('false', $form->first()->attr('data-turbo'), 'form must have data-turbo="false" to prevent Turbo from intercepting multipart upload');
    }

    /**
     * Bug regression (Bug 1 + Bug 2 + Bug 3): uploading a real Degiro CSV with
     * cross-currency commissions must not crash and must render the results page.
     *
     * - Bug 1: cross-currency commission (EUR commission on USD stock) used to throw
     *          CurrencyMismatchException inside resolveCommission().
     * - Bug 2: BigDecimal values rendered in Twig via toFloat() used to lose precision
     *          or throw on certain Twig filter combinations.
     * - Bug 3: data-turbo="false" on the form required for full-page response (not Turbo frame).
     */
    public function testPostUploadWithValidDegiroCSVRendersResultsPage(): void
    {
        $client = $this->createAuthenticatedClient();

        // Step 1: GET /import to obtain a valid CSRF token
        $crawler = $client->request('GET', '/import');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_token"]')->first()->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token input not found on /import page');

        // Step 2: Copy fixture to a temp path that UploadedFile can safely move
        $fixturePath = __DIR__ . '/../Fixtures/degiro_transactions_sample.csv';
        $tempPath    = sys_get_temp_dir() . '/degiro_test_' . uniqid() . '.csv';
        copy($fixturePath, $tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'degiro_transactions_sample.csv',
            'text/csv',
            null,
            true, // mark as already moved (test mode)
        );

        // Step 3: POST to /import/upload with a valid CSRF token and broker file
        $client->request(
            'POST',
            '/import/upload',
            [
                '_token'    => $csrfToken,
                'broker_id' => 'degiro_transactions',
            ],
            [
                'broker_file' => $uploadedFile,
            ],
        );

        // Bug regression: with data-turbo="false" the controller renders results directly
        // (no redirect), so we expect HTTP 200, not 302.
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('h1', 'Wynik importu');
        self::assertSelectorExists('table');
    }
}
