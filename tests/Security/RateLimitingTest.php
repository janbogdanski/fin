<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P2-112 — Rate-limiting code path verification.
 *
 * AuthController::requestMagicLink() guards against abuse with two independent
 * limiters: one keyed on the client IP and one on the email address. In the
 * default test environment both limiters are configured with limit: 100 to
 * avoid false positives. This test class wires the controller with
 * "tight" limiters (limit: 1, declared in rate_limiter.yaml when@test:) so
 * that the rejection branch is reachable with just two requests.
 *
 * Isolation: KernelBrowser reboots the kernel after every request by default,
 * which would discard the in-memory ArrayAdapter cache and reset the limiter
 * state. We call disableReboot() so that the kernel — and therefore the
 * limiter state — persists for the duration of the test method. Each test
 * method still gets a fresh kernel because createClient() always boots a new
 * one (and WebTestCase::tearDown shuts it down).
 *
 * @group security
 */
final class RateLimitingTest extends WebTestCase
{
    /**
     * Two consecutive POSTs to /login with the same email address trigger the
     * per-email rate limiter on the second request.
     *
     * AuthController checks the IP limiter first, then the email limiter. With
     * magic_link_ip_tight at limit: 1, the IP bucket is exhausted after the
     * first request, meaning the second request may be rejected by either
     * limiter — the observable result (redirect + flash) is identical in both
     * cases. What matters is that the rate-limit branch is executed.
     */
    public function testSecondRequestIsRateLimited(): void
    {
        $client = self::createClient();

        // Prevent the kernel from rebooting between requests so that the
        // in-memory ArrayAdapter (used for rate limiter storage in tests)
        // keeps its state across HTTP calls within this test.
        $client->disableReboot();

        // Warm up the session and obtain a valid CSRF token from the rendered form.
        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotEmpty($csrfToken, 'CSRF token must be present in the login form.');

        $email = 'rate-limit-test@example.com';

        // First POST — should be accepted (one available token in each tight limiter).
        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        $firstStatusCode = $client->getResponse()->getStatusCode();

        // If the first request was already rejected by the rate limiter the test
        // configuration is wrong — fail early with a clear message.
        if ($firstStatusCode === 302) {
            $crawler = $client->followRedirect();
            self::fail(
                'First POST was unexpectedly rejected. Page text: ' . $crawler->text(),
            );
        }

        self::assertSame(
            200,
            $firstStatusCode,
            'First POST to /login must succeed (email_sent page).',
        );

        // Second POST with the same email and a fresh CSRF token.
        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        // The rate limiter must reject the second request and redirect back to /login.
        self::assertResponseRedirects('/login', 302, 'Second POST must be rate-limited (302 to /login).');

        $crawler = $client->followRedirect();

        // Verify the rate-limit flash is rendered — "Zbyt wiele prob logowania".
        $errorDivs = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(
            0,
            $errorDivs->count(),
            'Expected a rate-limit error flash after the second POST.',
        );

        $flashText = $errorDivs->first()->text();
        self::assertStringContainsString(
            'Zbyt wiele prob',
            $flashText,
            'Flash message must indicate too many login attempts.',
        );
    }

    /**
     * A request from an IP address that has already exhausted its IP-level
     * budget is rejected before the email limiter is ever checked.
     *
     * Because magic_link_ip_tight has limit: 1, the IP bucket (keyed on
     * 127.0.0.1 in KernelBrowser) is exhausted after the first successful
     * request. A second request using a different email must still be
     * rejected, confirming the IP check fires independently of the email key.
     */
    public function testIpRateLimitIsCheckedBeforeEmailLimiter(): void
    {
        $client = self::createClient();

        // Keep the kernel alive between requests to preserve the ArrayAdapter state.
        $client->disableReboot();

        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotEmpty($csrfToken);

        // First request: exhausts both the IP bucket and the email bucket for this address.
        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => 'rate-limit-ip-first@example.com',
        ]);

        $firstStatusCode = $client->getResponse()->getStatusCode();

        if ($firstStatusCode === 302) {
            $crawler = $client->followRedirect();
            self::fail(
                'First POST was unexpectedly rejected. Page text: ' . $crawler->text(),
            );
        }

        self::assertSame(200, $firstStatusCode, 'First POST must succeed.');

        // Second request: different email, same IP — the IP limiter must reject it.
        $crawler = $client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            '_csrf_token' => $csrfToken,
            'email' => 'rate-limit-ip-second@example.com',
        ]);

        self::assertResponseRedirects('/login', 302, 'Second POST (different email, same IP) must be rate-limited.');

        $crawler = $client->followRedirect();

        $errorDivs = $crawler->filter('div.bg-red-50');
        self::assertGreaterThan(
            0,
            $errorDivs->count(),
            'Expected a rate-limit error flash for IP-level rejection.',
        );

        $flashText = $errorDivs->first()->text();
        self::assertStringContainsString(
            'Zbyt wiele prob',
            $flashText,
            'Flash message must indicate too many login attempts.',
        );
    }
}
