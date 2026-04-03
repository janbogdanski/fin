<?php

declare(strict_types=1);

namespace App\Tests\Chaos;

use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use App\ExchangeRate\Infrastructure\NBP\NBPApiClient;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @group chaos
 *
 * Simulates NBP API infrastructure failures:
 * - Timeouts
 * - Malformed JSON responses
 * - 500 Internal Server Errors
 * - Empty response bodies
 */
final class NBPApiChaosTest extends TestCase
{
    private PolishWorkingDayResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PolishWorkingDayResolver();
    }

    public function testTimeoutThrowsRuntimeException(): void
    {
        $mockClient = new MockHttpClient(
            static fn (): MockResponse => throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection timed out'),
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed after \d+ retries/');

        $client->getRateForDate(
            CurrencyCode::USD,
            new \DateTimeImmutable('2026-03-17'),
        );
    }

    public function testMalformedJsonThrowsRuntimeException(): void
    {
        $mockClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(
                '{this is not valid json!!!',
                [
                    'http_code' => 200,
                ],
            ),
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        $this->expectException(\RuntimeException::class);

        $client->getRateForDate(
            CurrencyCode::USD,
            new \DateTimeImmutable('2026-03-17'),
        );
    }

    public function testServerError500ExhaustsRetriesAndThrows(): void
    {
        $callCount = 0;
        $mockClient = new MockHttpClient(
            static function () use (&$callCount): MockResponse {
                $callCount++;

                return new MockResponse('Internal Server Error', [
                    'http_code' => 500,
                ]);
            },
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed after \d+ retries/');

        try {
            $client->getRateForDate(
                CurrencyCode::USD,
                new \DateTimeImmutable('2026-03-17'),
            );
        } finally {
            // Should have retried MAX_RETRIES (3) * MAX_FALLBACK_DAYS (7) = 21 requests max
            self::assertGreaterThanOrEqual(3, $callCount, 'Should retry at least MAX_RETRIES times');
        }
    }

    public function testEmptyResponseBodyFallsBackAndEventuallyThrows(): void
    {
        $mockClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', [
                'http_code' => 200,
            ]),
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        $this->expectException(\RuntimeException::class);

        $client->getRateForDate(
            CurrencyCode::USD,
            new \DateTimeImmutable('2026-03-17'),
        );
    }

    public function testOversizedResponseRejected(): void
    {
        $oversizedBody = str_repeat('x', 1_048_577); // 1 byte over 1MB limit
        $mockClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse($oversizedBody, [
                'http_code' => 200,
            ]),
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        // The oversized check throws RuntimeException("response too large"),
        // but request() retries catch it. After exhausting retries, it wraps as
        // "failed after N retries" with the original as previous exception.
        try {
            $client->getRateForDate(
                CurrencyCode::USD,
                new \DateTimeImmutable('2026-03-17'),
            );
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            // Walk the exception chain — "response too large" should be in the cause
            $cause = $e->getPrevious();
            self::assertNotNull($cause, 'Should have a previous exception');
            self::assertStringContainsString('response too large', $cause->getMessage());
        }
    }

    public function testAllDates404ThrowsExchangeRateNotFound(): void
    {
        $mockClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('', [
                'http_code' => 404,
            ]),
        );

        $client = new NBPApiClient($mockClient, $this->resolver);

        $this->expectException(ExchangeRateNotFoundException::class);

        $client->getRateForDate(
            CurrencyCode::USD,
            new \DateTimeImmutable('2026-03-17'),
        );
    }
}
