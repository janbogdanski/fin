<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\Integration\AuthenticatedWebTestCase;

/**
 * E2E: Authenticated user navigates Dashboard -> Declaration preview.
 *
 * With no imported data, the preview should redirect to import with guidance.
 * Tests the full declaration access flow including auth guard.
 *
 * @group e2e
 */
final class DeclarationPreviewFlowTest extends AuthenticatedWebTestCase
{
    public function testDashboardToDeclarationPreviewRedirectsWhenNoData(): void
    {
        $client = $this->createAuthenticatedClient();

        // Step 1: Visit dashboard
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        // Step 2: Try to access PIT-38 preview
        $client->request('GET', '/declaration/2025/preview');

        // No data -> redirects to import
        self::assertResponseRedirects();
        self::assertStringContainsString('/import', (string) $client->getResponse()->headers->get('Location'));

        // Step 3: Follow redirect, verify flash message
        $crawler = $client->followRedirect();
        $pageText = mb_strtolower($crawler->text());

        self::assertTrue(
            str_contains($pageText, 'brak danych')
            || str_contains($pageText, 'csv')
            || str_contains($pageText, 'import'),
            'Flash message should guide user to import data',
        );
    }

    public function testDeclarationPreviewRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/declaration/2025/preview');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testDeclarationExportXmlWithNoDataRedirectsToImport(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/2025/export/xml');

        self::assertResponseRedirects();
        self::assertStringContainsString('/import', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testDeclarationInvalidYearReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/declaration/abcd/preview');

        self::assertResponseStatusCodeSame(404);
    }
}
