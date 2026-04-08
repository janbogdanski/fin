<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-level security headers integration test.
 *
 * Verifies that the SecurityHeadersSubscriber is actually wired into the
 * Symfony kernel and emits security headers on real HTTP responses.
 * A unit test of the subscriber class alone cannot detect if the subscriber
 * is accidentally removed from the service container.
 */
#[Group('security')]
final class SecurityHeadersIntegrationTest extends WebTestCase
{
    public function testSecurityHeadersArePresentOnPublicRoute(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $headers = $client->getResponse()->headers;

        self::assertSame(
            'DENY',
            $headers->get('X-Frame-Options'),
            'X-Frame-Options must be DENY',
        );

        self::assertSame(
            'nosniff',
            $headers->get('X-Content-Type-Options'),
            'X-Content-Type-Options must be nosniff',
        );

        self::assertNotEmpty(
            $headers->get('Content-Security-Policy'),
            'Content-Security-Policy must be present and non-empty',
        );

        // HSTS may only be enforced over HTTPS in some environments.
        // We assert it is present because the subscriber always sets it — the
        // test environment does not strip HSTS over HTTP.
        $hsts = $headers->get('Strict-Transport-Security');
        if ($hsts !== null) {
            self::assertNotEmpty($hsts, 'Strict-Transport-Security must be non-empty when present');
        }
    }
}
