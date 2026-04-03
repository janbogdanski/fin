<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for AuthController (login flow).
 *
 * All auth routes are PUBLIC_ACCESS, so no loginUser() needed.
 */
final class AuthControllerWebTest extends WebTestCase
{
    public function testGetLoginReturns200(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testPostLoginWithoutCsrfRedirectsWithError(): void
    {
        $client = self::createClient();

        $client->request('POST', '/login', [
            'email' => 'user@example.com',
        ]);

        // Missing CSRF token should redirect back to login
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        self::assertStringContainsString('CSRF', $pageText);
    }

    public function testPostLoginWithEmptyEmailRedirectsWithError(): void
    {
        $client = self::createClient();

        // Generate a valid CSRF token first by loading the login page
        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->count() > 0
            ? $crawler->filter('input[name="_csrf_token"]')->attr('value')
            : '';

        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => '',
        ]);

        // Empty email should redirect back to login with error
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        // Polish error message about invalid email
        self::assertStringContainsString('e-mail', mb_strtolower($pageText));
    }

    public function testPostLoginWithInvalidEmailRedirectsWithError(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->count() > 0
            ? $crawler->filter('input[name="_csrf_token"]')->attr('value')
            : '';

        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => 'not-an-email',
        ]);

        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $pageText = $crawler->text();

        self::assertStringContainsString('e-mail', mb_strtolower($pageText));
    }

    public function testAuthVerifyRouteExists(): void
    {
        $client = self::createClient();

        // The auth/verify route is intercepted by MagicLinkAuthenticator.
        // With an invalid token, it should redirect to login (authenticator failure).
        $client->request('GET', '/auth/verify/invalid-token');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
