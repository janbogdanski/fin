<?php

declare(strict_types=1);

namespace App\Tests\Unit\ExchangeRate;

use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use App\ExchangeRate\Infrastructure\NBP\NBPApiClient;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NBPApiClientTest extends TestCase
{
    private PolishWorkingDayResolver $workingDayResolver;

    protected function setUp(): void
    {
        $this->workingDayResolver = new PolishWorkingDayResolver();
    }

    public function testReturnsRateForRegularWeekday(): void
    {
        // Transaction on Wednesday 2025-03-19 → rate from Tuesday 2025-03-18
        $httpClient = new MockHttpClient([
            new MockResponse($this->rateJson('2025-03-18', '4.0512', '055/A/NBP/2025')),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        $rate = $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-19'));

        self::assertTrue($rate->rate()->isEqualTo('4.0512'));
        self::assertSame('2025-03-18', $rate->effectiveDate()->format('Y-m-d'));
        self::assertSame('055/A/NBP/2025', $rate->tableNumber());
        self::assertTrue($rate->currency()->equals(CurrencyCode::USD));
    }

    public function testFridayRateForMondayTransaction(): void
    {
        // Transaction on Monday 2025-03-17 → rate from Friday 2025-03-14
        $httpClient = new MockHttpClient([
            new MockResponse($this->rateJson('2025-03-14', '4.0300', '052/A/NBP/2025')),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        $rate = $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-17'));

        self::assertSame('2025-03-14', $rate->effectiveDate()->format('Y-m-d'));
        self::assertTrue($rate->rate()->isEqualTo('4.0300'));
    }

    public function testFridayRateForWeekendTransaction(): void
    {
        // Transaction on Saturday 2025-03-15 → rate from Friday 2025-03-14
        $httpClient = new MockHttpClient([
            new MockResponse($this->rateJson('2025-03-14', '4.0300', '052/A/NBP/2025')),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        $rate = $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-15'));

        self::assertSame('2025-03-14', $rate->effectiveDate()->format('Y-m-d'));
    }

    public function testSkipsPolishHoliday(): void
    {
        // Transaction on 2025-05-02 (Friday). Previous day is 2025-05-01 (Święto Pracy).
        // Should go to 2025-04-30 (Wednesday).
        $httpClient = new MockHttpClient([
            new MockResponse($this->rateJson('2025-04-30', '3.9800', '084/A/NBP/2025')),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        $rate = $client->getRateForDate(CurrencyCode::EUR, new \DateTimeImmutable('2025-05-02'));

        self::assertSame('2025-04-30', $rate->effectiveDate()->format('Y-m-d'));
        self::assertTrue($rate->currency()->equals(CurrencyCode::EUR));
    }

    public function testThrowsWhenNoRateAvailableWithin7Days(): void
    {
        // API returns 404 for every attempt
        $responses = array_fill(0, 7, new MockResponse('', [
            'http_code' => 404,
        ]));
        $httpClient = new MockHttpClient($responses);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);

        $this->expectException(ExchangeRateNotFoundException::class);
        $this->expectExceptionMessageMatches('/USD.*2025-03-14/');

        $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-15'));
    }

    public function testBatchFetchReturnsRatesForRange(): void
    {
        $json = json_encode([
            'table' => 'A',
            'currency' => 'dolar amerykański',
            'code' => 'USD',
            'rates' => [
                [
                    'no' => '050/A/NBP/2025',
                    'effectiveDate' => '2025-03-12',
                    'mid' => 4.0412,
                ],
                [
                    'no' => '051/A/NBP/2025',
                    'effectiveDate' => '2025-03-13',
                    'mid' => 4.0500,
                ],
                [
                    'no' => '052/A/NBP/2025',
                    'effectiveDate' => '2025-03-14',
                    'mid' => 4.0512,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient([
            new MockResponse($json),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        $rates = $client->getRatesForDateRange(
            CurrencyCode::USD,
            new \DateTimeImmutable('2025-03-12'),
            new \DateTimeImmutable('2025-03-14'),
        );

        self::assertCount(3, $rates);
        self::assertArrayHasKey('USD_2025-03-12', $rates);
        self::assertArrayHasKey('USD_2025-03-13', $rates);
        self::assertArrayHasKey('USD_2025-03-14', $rates);
        self::assertTrue($rates['USD_2025-03-14']->rate()->isEqualTo('4.0512'));
    }

    public function testFallsBackToPreviousWorkingDayOn404(): void
    {
        // First request (Friday) → 404, second request (Thursday) → success
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 404,
            ]),
            new MockResponse($this->rateJson('2025-03-13', '4.0500', '051/A/NBP/2025')),
        ]);

        $client = new NBPApiClient($httpClient, $this->workingDayResolver);
        // Transaction on Saturday → resolver picks Friday → 404 → falls back to Thursday
        $rate = $client->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-15'));

        self::assertSame('2025-03-13', $rate->effectiveDate()->format('Y-m-d'));
    }

    private function rateJson(string $date, string $mid, string $tableNo): string
    {
        return json_encode([
            'table' => 'A',
            'currency' => 'dolar amerykański',
            'code' => 'USD',
            'rates' => [
                [
                    'no' => $tableNo,
                    'effectiveDate' => $date,
                    'mid' => (float) $mid,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
