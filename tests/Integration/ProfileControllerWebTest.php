<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/**
 * Integration tests for ProfileController.
 *
 * Requires authentication -- uses AuthenticatedWebTestCase.
 */
final class ProfileControllerWebTest extends AuthenticatedWebTestCase
{
    public function testGetProfileReturns200WhenAuthenticated(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
    }

    public function testProfilePageContainsFormFields(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();

        // Profile page should have NIP and name fields
        self::assertGreaterThan(0, $crawler->filter('input[name="nip"]')->count(), 'Profile page should have NIP input');
        self::assertGreaterThan(0, $crawler->filter('input[name="first_name"]')->count(), 'Profile page should have first_name input');
        self::assertGreaterThan(0, $crawler->filter('input[name="last_name"]')->count(), 'Profile page should have last_name input');
    }

    public function testPostProfileWithInvalidCsrfRedirects(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/profile', [
            '_csrf_token' => 'invalid',
            'nip' => '1234567890',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        self::assertResponseRedirects('/profile');

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        self::assertStringContainsString('CSRF', $pageText);
    }

    public function testPostReferralWithInvalidCsrfRedirects(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/profile/referral', [
            '_csrf_token' => 'invalid',
            'referral_code' => 'SOME-CODE',
        ]);

        self::assertResponseRedirects('/profile');

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        self::assertStringContainsString('CSRF', $pageText);
    }
}
