<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E2E: User opens login page, enters email, submits magic link request.
 *
 * Tests the full login form submission flow including CSRF protection.
 */
#[Group('e2e')]
final class MagicLinkRequestFlowTest extends WebTestCase
{
    public function testValidEmailSubmissionShowsEmailSentConfirmation(): void
    {
        $client = self::createClient();

        // Step 1: Load login page
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        // Step 2: Extract CSRF token from the form
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token must be present in login form');

        // Step 3: Submit the form with a valid email
        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => 'e2etest@example.com',
        ]);

        // Step 4: Should render email_sent confirmation (200, not redirect)
        self::assertResponseIsSuccessful();

        $pageText = $client->getCrawler()->text();
        self::assertTrue(
            str_contains(mb_strtolower($pageText), 'e2etest@example.com')
            || str_contains(mb_strtolower($pageText), 'email')
            || str_contains(mb_strtolower($pageText), 'wys'),
            'Email sent confirmation page should mention the email or sending',
        );
    }

    public function testInvalidEmailSubmissionRedirectsBackWithError(): void
    {
        $client = self::createClient();

        // Step 1: Load login page, get CSRF
        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Step 2: Submit with invalid email
        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => 'not-an-email',
        ]);

        // Step 3: Should redirect back to login
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        $pageText = mb_strtolower($crawler->text());

        self::assertStringContainsString('e-mail', $pageText);
    }

    public function testSubmissionWithoutCsrfIsRejected(): void
    {
        $client = self::createClient();

        $client->request('POST', '/login', [
            'email' => 'user@example.com',
        ]);

        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        self::assertStringContainsString('CSRF', $crawler->text());
    }
}
