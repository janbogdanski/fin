<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Integration tests for PriorYearLossController routing and CSRF validation.
 */
final class PriorYearLossControllerWebTest extends AuthenticatedWebTestCase
{
    /**
     * AC2: POST /losses with invalid CSRF token redirects with error.
     */
    public function testPostWithInvalidCsrfRedirects(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/losses', [
            '_token' => 'invalid_token',
            'loss_year' => '2023',
            'tax_category' => 'EQUITY',
            'amount' => '10000.00',
        ]);

        self::assertResponseRedirects('/losses');
    }

    /**
     * Verify POST route exists and accepts requests.
     */
    public function testPostRouteExists(): void
    {
        $client = $this->createAuthenticatedClient();

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
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/losses/00000000-0000-0000-0000-000000000001/delete', [
            '_token' => 'bad',
        ]);

        // 302 redirect (not 404) confirms route is registered
        self::assertResponseRedirects('/losses');
    }
}
