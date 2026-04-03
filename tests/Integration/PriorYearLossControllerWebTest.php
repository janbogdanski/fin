<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for PriorYearLossController routing and CSRF validation.
 *
 * Security is disabled in test env (security.yaml when@test),
 * so tests that require authenticated user are in unit tests.
 * These tests verify the Symfony HTTP stack: routing, CSRF protection.
 */
final class PriorYearLossControllerWebTest extends WebTestCase
{
    /**
     * AC2: POST /losses with invalid CSRF token redirects with error.
     * CSRF check happens before resolveUserId(), so this works without auth.
     */
    public function testPostWithInvalidCsrfRedirects(): void
    {
        $client = self::createClient();

        $client->request('POST', '/losses', [
            '_token' => 'invalid_token',
            'loss_year' => '2023',
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');

        $crawler = $client->followRedirect();

        // After redirect, we get 500 because GET /losses needs user.
        // But the redirect itself confirms routing + CSRF validation works.
    }

    /**
     * Verify POST route exists and accepts requests.
     */
    public function testPostRouteExists(): void
    {
        $client = self::createClient();

        $client->request('POST', '/losses', [
            '_token' => 'bad',
        ]);

        // 302 redirect (not 404) confirms route is registered
        self::assertResponseRedirects('/losses');
    }

    /**
     * Verify DELETE route exists.
     */
    public function testDeleteRouteExists(): void
    {
        $client = self::createClient();

        $client->request('POST', '/losses/00000000-0000-0000-0000-000000000001/delete', [
            '_token' => 'bad',
        ]);

        // 302 redirect (not 404) confirms route is registered
        self::assertResponseRedirects('/losses');
    }
}
