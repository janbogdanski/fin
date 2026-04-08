<?php

declare(strict_types=1);

namespace App\Tests\Canary;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Canary test: verifies NBP API response format hasn't changed.
 *
 * Hits the REAL api.nbp.pl endpoint. Run nightly in CI, not in the regular test suite.
 * Catches API breaking changes (renamed fields, removed endpoints, changed types)
 * before they reach users.
 */
#[Group('canary')]
final class NBPApiCanaryTest extends TestCase
{
    private const string NBP_BASE_URL = 'https://api.nbp.pl/api/exchangerates/rates/a';

    /**
     * A known historical date that will always have a published rate.
     * 2025-03-18 is a Tuesday -- NBP publishes rates on working days.
     */
    private const string KNOWN_PUBLISHING_DATE = '2025-03-18';

    /**
     * A known non-publishing day (Saturday).
     */
    private const string KNOWN_NON_PUBLISHING_DATE = '2025-03-15';

    public function testSingleDayRateFormatUnchanged(): void
    {
        $client = HttpClient::create([
            'timeout' => 10,
        ]);
        $response = $client->request(
            'GET',
            sprintf('%s/usd/%s/?format=json', self::NBP_BASE_URL, self::KNOWN_PUBLISHING_DATE),
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();

        // Top-level structure
        self::assertArrayHasKey('table', $data);
        self::assertArrayHasKey('currency', $data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('rates', $data);

        self::assertSame('A', $data['table']);
        self::assertSame('USD', $data['code']);

        // Rates array
        self::assertIsArray($data['rates']);
        self::assertNotEmpty($data['rates']);

        $rate = $data['rates'][0];
        self::assertArrayHasKey('no', $rate);
        self::assertArrayHasKey('effectiveDate', $rate);
        self::assertArrayHasKey('mid', $rate);

        // Type assertions
        self::assertIsString($rate['no']);
        self::assertMatchesRegularExpression('/^\d{3}\/A\/NBP\/\d{4}$/', $rate['no']);
        self::assertSame(self::KNOWN_PUBLISHING_DATE, $rate['effectiveDate']);
        self::assertIsFloat($rate['mid']);
        self::assertGreaterThan(0.0, $rate['mid']);
    }

    public function testDateRangeFormatUnchanged(): void
    {
        $client = HttpClient::create([
            'timeout' => 10,
        ]);
        $response = $client->request(
            'GET',
            sprintf('%s/usd/2025-03-12/2025-03-14/?format=json', self::NBP_BASE_URL),
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();

        // Same top-level structure as single-day
        self::assertArrayHasKey('table', $data);
        self::assertArrayHasKey('currency', $data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('rates', $data);

        self::assertSame('A', $data['table']);
        self::assertSame('USD', $data['code']);

        // Date range should return multiple rates (Wed-Fri = 3 working days)
        self::assertIsArray($data['rates']);
        self::assertGreaterThanOrEqual(2, \count($data['rates']), 'Date range should return multiple rates');

        foreach ($data['rates'] as $rate) {
            self::assertArrayHasKey('no', $rate);
            self::assertArrayHasKey('effectiveDate', $rate);
            self::assertArrayHasKey('mid', $rate);

            self::assertIsString($rate['no']);
            self::assertIsString($rate['effectiveDate']);
            self::assertIsFloat($rate['mid']);
            self::assertGreaterThan(0.0, $rate['mid']);
        }
    }

    public function test404ForNonPublishingDay(): void
    {
        $client = HttpClient::create([
            'timeout' => 10,
        ]);
        $response = $client->request(
            'GET',
            sprintf('%s/usd/%s/?format=json', self::NBP_BASE_URL, self::KNOWN_NON_PUBLISHING_DATE),
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
