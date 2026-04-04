<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\Integration\AuthenticatedWebTestCase;

/**
 * E2E: Authenticated user navigates Dashboard -> Losses -> adds a loss -> verifies -> deletes.
 *
 * Full CRUD flow for prior year loss management.
 *
 * @group e2e
 */
final class LossManagementFlowTest extends AuthenticatedWebTestCase
{
    public function testFullLossAddAndDeleteFlow(): void
    {
        $client = $this->createAuthenticatedClient();

        // Step 1: Start at dashboard
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        // Step 2: Navigate to losses page
        $crawler = $client->request('GET', '/losses');
        self::assertResponseIsSuccessful();

        // Step 3: Extract CSRF and add a loss
        $csrfToken = $crawler->filter('form[action*="/losses"] input[name="_token"]')->first()->attr('value');
        self::assertNotEmpty($csrfToken);

        $lossYear = '2025';

        $client->request('POST', '/losses', [
            '_token' => $csrfToken,
            'loss_year' => $lossYear,
            'tax_category' => 'EQUITY',
            'amount' => '25000.50',
        ]);

        self::assertResponseRedirects('/losses');

        // Step 4: Follow redirect — verify success flash and loss visible
        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        self::assertStringContainsString('Dodano strate', $pageText);
        self::assertStringContainsString('25', $pageText); // amount fragment visible

        // Step 5: Find the delete form and delete the loss
        $deleteForm = $crawler->filter('form[action*="/losses/"][action*="/delete"]');
        self::assertGreaterThan(0, $deleteForm->count(), 'Delete form must be present after adding a loss');

        $deleteAction = (string) $deleteForm->first()->attr('action');
        $deleteCsrfToken = (string) $deleteForm->first()->filter('input[name="_token"]')->attr('value');

        $client->request('POST', $deleteAction, [
            '_token' => $deleteCsrfToken,
        ]);

        self::assertResponseRedirects('/losses');

        // Step 6: Verify deletion flash
        $crawler = $client->followRedirect();
        self::assertStringContainsString('usunieta', $crawler->text());
    }

    public function testLossPageRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/losses');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
