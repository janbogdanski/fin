<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies that the /health endpoint rate limiter rejects requests
 * once the budget is exhausted.
 *
 * In the test environment, HealthController is wired with a tight limiter
 * (health_check_tight, limit: 1) via services.yaml when@test:. This allows
 * testing the rejection path with just two requests instead of 31.
 *
 * disableReboot() preserves the persistent ArrayAdapter state between requests
 * so that the limiter token is not reset after the first call.
 */
#[Group('security')]
final class HealthRateLimitingTest extends WebTestCase
{
    public function testSecondRequestIsRateLimited(): void
    {
        $client = self::createClient();

        // Preserve kernel state (and therefore the in-memory limiter state) between
        // requests — same technique as RateLimitingTest for auth endpoints.
        $client->disableReboot();

        // First request — must return 200 (one token available in tight limiter).
        $client->request('GET', '/health');
        self::assertResponseStatusCodeSame(200, 'First GET /health must succeed.');

        $first = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('ok', $first['status'], 'First response must report status: ok.');

        // Second request — tight limiter (limit: 1) is exhausted; must return 429.
        $client->request('GET', '/health');
        self::assertResponseStatusCodeSame(429, 'Second GET /health must be rate-limited (429).');

        $second = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $second, 'Rate-limited response must contain an error key.');
    }
}
