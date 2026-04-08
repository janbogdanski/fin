<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Security smoke tests: verify that all auth-required routes redirect
 * unauthenticated users to /login.
 *
 * These tests ensure the access_control configuration in security.yaml
 * correctly protects sensitive endpoints. If any route silently allows
 * anonymous access, these tests will catch it.
 */
final class AuthEnforcementTest extends WebTestCase
{
    #[DataProvider('protectedRoutesProvider')]
    public function testUnauthenticatedAccessRedirectsToLogin(string $method, string $url): void
    {
        $client = self::createClient();

        $client->request($method, $url);

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        $location = $response->headers->get('Location') ?? '';

        self::assertSame(
            302,
            $statusCode,
            sprintf(
                'Expected 302 redirect for unauthenticated %s %s, got %d',
                $method,
                $url,
                $statusCode,
            ),
        );

        self::assertStringContainsString(
            '/login',
            $location,
            sprintf(
                'Expected redirect to /login for %s %s, got Location: %s',
                $method,
                $url,
                $location,
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedRoutesProvider(): iterable
    {
        // Dashboard
        yield 'GET /dashboard' => ['GET', '/dashboard'];
        yield 'GET /dashboard/calculation/2025' => ['GET', '/dashboard/calculation/2025'];
        yield 'GET /dashboard/fifo/2025' => ['GET', '/dashboard/fifo/2025'];
        yield 'GET /dashboard/dividends/2025' => ['GET', '/dashboard/dividends/2025'];

        // Import
        yield 'GET /import' => ['GET', '/import'];
        yield 'POST /import/upload' => ['POST', '/import/upload'];

        // Profile
        yield 'GET /profile' => ['GET', '/profile'];
        yield 'POST /profile' => ['POST', '/profile'];
        yield 'POST /profile/referral' => ['POST', '/profile/referral'];

        // Declaration
        yield 'GET /declaration/2025/preview' => ['GET', '/declaration/2025/preview'];
        yield 'GET /declaration/2025/export/xml' => ['GET', '/declaration/2025/export/xml'];
        yield 'GET /declaration/2025/export/pdf' => ['GET', '/declaration/2025/export/pdf'];
        yield 'GET /declaration/2025/pitzg/US' => ['GET', '/declaration/2025/pitzg/US'];

        // Billing checkout (POST only, webhook is public)
        yield 'POST /billing/checkout' => ['POST', '/billing/checkout'];

        // Losses
        yield 'GET /losses' => ['GET', '/losses'];
        yield 'POST /losses' => ['POST', '/losses'];
        yield 'POST /losses/{id}/delete' => ['POST', '/losses/00000000-0000-0000-0000-000000000099/delete'];
    }

    /**
     * Verify that public routes remain accessible without authentication.
     */
    #[DataProvider('publicRoutesProvider')]
    public function testPublicRoutesAreAccessible(string $method, string $url): void
    {
        $client = self::createClient();

        $client->request($method, $url);

        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();

        // Public routes should NOT redirect to /login
        if ($statusCode === 302) {
            $location = $response->headers->get('Location') ?? '';
            self::assertStringNotContainsString(
                '/login',
                $location,
                sprintf(
                    'Public route %s %s should not redirect to /login, got Location: %s',
                    $method,
                    $url,
                    $location,
                ),
            );
        } else {
            // Accept 200 (OK) or 400 (bad request for webhooks) as valid responses
            self::assertContains(
                $statusCode,
                [200, 400],
                sprintf(
                    'Public route %s %s returned unexpected status %d',
                    $method,
                    $url,
                    $statusCode,
                ),
            );
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function publicRoutesProvider(): iterable
    {
        yield 'GET /' => ['GET', '/'];
        yield 'GET /login' => ['GET', '/login'];
        yield 'GET /cennik' => ['GET', '/cennik'];
        yield 'GET /blog' => ['GET', '/blog'];
        yield 'POST /billing/webhook' => ['POST', '/billing/webhook'];
    }
}
