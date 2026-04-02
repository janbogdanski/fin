<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P2-022: Basic integration test for ImportController via HTTP client.
 *
 * Uses WebTestCase (browser-kit) to test the controller through the
 * full Symfony HTTP stack -- routing, middleware, response codes.
 *
 * Security is disabled in test env (security.yaml when@test),
 * so no authentication setup is needed.
 */
final class ImportControllerWebTest extends WebTestCase
{
    public function testGetImportReturns200(): void
    {
        $client = self::createClient();

        $client->request('GET', '/import');

        self::assertResponseIsSuccessful();
    }

    public function testPostUploadWithoutFileRedirectsWithError(): void
    {
        $client = self::createClient();

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
        $client = self::createClient();

        $client->request('POST', '/import/upload', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();

        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(0, $errorFlash->count(), 'Expected CSRF error flash');
        self::assertStringContainsString('CSRF', $errorFlash->text());
    }
}
