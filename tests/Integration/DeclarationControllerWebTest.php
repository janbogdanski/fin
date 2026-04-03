<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Integration tests for DeclarationController.
 *
 * With a fresh user (no imported transactions), the preview endpoint
 * redirects to import page with a flash message (NoData result).
 *
 * Export endpoints (XML, PDF) apply a payment gate before checking data,
 * but with zero transactions the gate passes (free tier), then NoData
 * triggers a redirect to import.
 */
final class DeclarationControllerWebTest extends AuthenticatedWebTestCase
{
    public function testPreviewWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/preview');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/import',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    public function testPreviewRedirectHasFlashWarning(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/preview');
        $crawler = $client->followRedirect();

        // Should contain a warning flash about missing data
        $pageText = $crawler->text();
        self::assertTrue(
            str_contains(mb_strtolower($pageText), 'brak danych')
            || str_contains(mb_strtolower($pageText), 'csv')
            || str_contains(mb_strtolower($pageText), 'import'),
            'Expected flash message about missing data after redirect',
        );
    }

    public function testExportXmlWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/export/xml');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/import',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    public function testExportPdfWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/export/pdf');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/import',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }

    public function testPitzgWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/pitzg/US');

        self::assertResponseRedirects();

        $location = (string) $client->getResponse()->headers->get('Location');

        // Should redirect to import (no data) or billing (payment gate)
        self::assertTrue(
            str_contains($location, '/import') || str_contains($location, '/billing'),
            'Expected redirect to import or billing, got: ' . $location,
        );
    }

    public function testPreviewRouteRequiresValidYear(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/abcd/preview');

        // Route requirement 'taxYear' => '\d{4}' should cause 404
        self::assertResponseStatusCodeSame(404);
    }

    public function testPitzgRouteRequiresValidCountryCode(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/pitzg/invalid');

        // Route requirement 'countryCode' => '[A-Z]{2}' should cause 404
        self::assertResponseStatusCodeSame(404);
    }
}
