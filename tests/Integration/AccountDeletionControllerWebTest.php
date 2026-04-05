<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Integration tests for the account deletion (GDPR art. 17) flow.
 *
 * Verifies:
 * - GET /account/delete returns 200 with confirmation form
 * - POST /account/delete with invalid CSRF redirects back with error
 * - POST /account/delete with valid CSRF anonymizes the user and redirects to /
 */
final class AccountDeletionControllerWebTest extends AuthenticatedWebTestCase
{
    public function testGetDeleteConfirmationReturns200WhenAuthenticated(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/account/delete');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action*="account/delete"]');
    }

    public function testGetDeleteConfirmationContainsCsrfField(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/account/delete');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('input[name="_csrf_token"]')->count(),
            'Confirmation form must contain a CSRF token field',
        );
    }

    public function testGetDeleteConfirmationContainsSubmitButton(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/account/delete');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('button[type="submit"]')->count(),
            'Confirmation form must contain a submit button',
        );
    }

    public function testPostDeleteWithInvalidCsrfRedirectsWithError(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/account/delete', [
            '_csrf_token' => 'invalid-token',
        ]);

        self::assertResponseRedirects('/account/delete');

        $crawler = $client->followRedirect();
        self::assertStringContainsString('CSRF', $crawler->text());
    }

    public function testPostDeleteWithValidCsrfAnonymizesAndRedirectsToHome(): void
    {
        $client = $this->createAuthenticatedClient();

        // Obtain a valid CSRF token from the confirmation page
        $crawler = $client->request('GET', '/account/delete');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotEmpty($csrfToken);

        $client->request('POST', '/account/delete', [
            '_csrf_token' => $csrfToken,
        ]);

        // Should redirect to landing page (/)
        self::assertResponseRedirects('/');

        // Verify the user row in DB has anonymized_at set
        $container = self::getContainer();
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get(\Doctrine\DBAL\Connection::class);

        $row = $connection->fetchAssociative(
            'SELECT email, nip, first_name, last_name, anonymized_at FROM users WHERE id = :id',
            ['id' => self::TEST_USER_ID],
        );

        self::assertIsArray($row);
        self::assertNotNull($row['anonymized_at'], 'anonymized_at must be set after deletion');
        self::assertStringStartsWith('deleted-', (string) $row['email']);
        self::assertStringEndsWith('@deleted.invalid', (string) $row['email']);
        self::assertNull($row['nip']);
        self::assertNull($row['first_name']);
        self::assertNull($row['last_name']);
    }
}
