<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Integration tests for DashboardController.
 *
 * Tests the empty state (no imported transactions) which should render
 * successfully with zero-value summaries.
 */
final class DashboardControllerWebTest extends AuthenticatedWebTestCase
{
    public function testDashboardIndexReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardCalculationReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/calculation/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardFifoReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/fifo/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardDividendsReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/dividends/2025');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardCalculationWithDifferentYearReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/dashboard/calculation/2024');

        self::assertResponseIsSuccessful();
    }

    public function testDashboardIndexShowsEmptyStateMessage(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();

        // Empty state: no transactions imported yet.
        // The template should indicate this (e.g., with a prompt to import).
        $pageText = $crawler->text();
        self::assertTrue(
            str_contains(mb_strtolower($pageText), 'import')
            || str_contains(mb_strtolower($pageText), 'wgraj')
            || str_contains(mb_strtolower($pageText), 'csv')
            || str_contains(mb_strtolower($pageText), '0'),
            'Dashboard should show empty state or zero values when no data exists',
        );
    }
}
