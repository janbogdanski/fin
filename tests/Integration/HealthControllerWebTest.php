<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for HealthController.
 *
 * /health is PUBLIC_ACCESS — no auth required.
 * In test environment, cache uses array adapter (not Redis), so checkCache() succeeds.
 */
final class HealthControllerWebTest extends WebTestCase
{
    public function testHealthReturns200WithOkStatus(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('checks', $data);
        self::assertArrayHasKey('db', $data['checks']);
        self::assertArrayHasKey('cache', $data['checks']);
    }

    public function testHealthChecksAreBoolean(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertIsBool($data['checks']['db']);
        self::assertIsBool($data['checks']['cache']);
    }

    public function testHealthReturnsJson(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testHealthIsNotAccessibleViaPost(): void
    {
        $client = self::createClient();

        $client->request('POST', '/health');

        self::assertResponseStatusCodeSame(405);
    }
}
