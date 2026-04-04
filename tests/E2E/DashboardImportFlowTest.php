<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\Integration\AuthenticatedWebTestCase;

/**
 * E2E: Authenticated user navigates Dashboard -> Import page -> attempts upload.
 *
 * Tests the import journey for a logged-in user, including empty state handling
 * and CSRF-protected upload endpoint.
 */
final class DashboardImportFlowTest extends AuthenticatedWebTestCase
{
    public function testDashboardShowsEmptyStateWithImportPrompt(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();

        // Empty state block is present — "Brak danych" heading with an import CTA
        self::assertSelectorExists('h2', 'Empty-state heading must be present on dashboard');
        self::assertSelectorTextContains('h2', 'Brak danych');
    }

    public function testDashboardToImportPageNavigation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Step 1: Visit dashboard
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        // Step 2: Navigate to import page
        $crawler = $client->request('GET', '/import');
        self::assertResponseIsSuccessful();

        // Import page should show supported brokers and file upload form
        $pageText = mb_strtolower($crawler->text());
        self::assertTrue(
            str_contains($pageText, 'interactive brokers')
            || str_contains($pageText, 'revolut')
            || str_contains($pageText, 'csv')
            || str_contains($pageText, 'broker'),
            'Import page should list supported brokers',
        );

        // Upload form must exist
        self::assertSelectorExists('form');
    }

    public function testImportUploadWithoutFileShowsError(): void
    {
        $client = $this->createAuthenticatedClient();

        // Step 1: Load import page
        $client->request('GET', '/import');
        self::assertResponseIsSuccessful();

        // Step 2: POST upload without file or CSRF -> redirect with error
        $client->request('POST', '/import/upload');

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();

        // Should show error flash
        $errorFlash = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(0, $errorFlash->count(), 'Error flash should appear after upload without file');
    }

    public function testImportUploadWithInvalidCsrfShowsError(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/import/upload', [
            '_token' => 'forged-csrf-token',
        ]);

        self::assertResponseRedirects('/import');

        $crawler = $client->followRedirect();
        self::assertStringContainsString('CSRF', $crawler->filter('div.bg-red-50')->text());
    }
}
