<?php

declare(strict_types=1);

namespace App\ExchangeRate\Infrastructure\NBP;

use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\ExchangeRate\Domain\Service\PolishWorkingDayResolver;
use App\Shared\Domain\PolishTimezone;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NBPApiClient implements ExchangeRateProviderInterface
{
    private const string BASE_URL = 'https://api.nbp.pl/api/exchangerates/rates/a';

    private const int TIMEOUT_SECONDS = 10;

    private const int MAX_RETRIES = 3;

    private const int MAX_FALLBACK_DAYS = 7;

    public function __construct(
        private HttpClientInterface $httpClient,
        private PolishWorkingDayResolver $workingDayResolver,
    ) {
    }

    public function getRateForDate(CurrencyCode $currency, \DateTimeImmutable $transactionDate): NBPRate
    {
        $targetDate = $this->workingDayResolver->resolveLastWorkingDayBefore($transactionDate);

        return $this->fetchRateWithFallback($currency, $targetDate);
    }

    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $url = sprintf(
            '%s/%s/%s/%s/?format=json',
            self::BASE_URL,
            strtolower($currency->value),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        $data = $this->request($url);

        if (! \is_array($data) || ! isset($data['rates']) || ! \is_array($data['rates'])) {
            return [];
        }

        $results = [];

        /** @var array{effectiveDate: string, mid: float|int|string, no: string} $rateData */
        foreach ($data['rates'] as $rateData) {
            $effectiveDate = new \DateTimeImmutable((string) $rateData['effectiveDate'], PolishTimezone::get());
            $key = \sprintf('%s_%s', $currency->value, $effectiveDate->format('Y-m-d'));

            $results[$key] = NBPRate::create(
                $currency,
                BigDecimal::of((string) $rateData['mid']),
                $effectiveDate,
                (string) $rateData['no'],
            );
        }

        return $results;
    }

    // TODO: P2-036 — Pre-warming optimization to reduce worst-case HTTP requests:
    //  Currently, each getRateForDate() call triggers up to MAX_FALLBACK_DAYS individual HTTP requests.
    //  For a batch of transactions spanning a year, this can mean hundreds of sequential requests.
    //  Optimization path:
    //  1. Add a warm(CurrencyCode, DateTimeImmutable $from, DateTimeImmutable $to) method
    //     that calls getRatesForDateRange() once and populates an in-memory cache (array keyed by currency_date).
    //  2. In getRateForDate(), check the cache first before hitting the API.
    //  3. The caller (e.g. FIFOMatchingService) should call warm() with the full date range before processing.
    //  This reduces worst case from N*MAX_FALLBACK_DAYS requests to 1 range request per currency.

    /**
     * Tries to fetch rate for the target date; on 404 falls back
     * to previous working days (max MAX_FALLBACK_DAYS attempts).
     */
    private function fetchRateWithFallback(CurrencyCode $currency, \DateTimeImmutable $targetDate): NBPRate
    {
        $candidate = $targetDate;

        for ($attempt = 0; $attempt < self::MAX_FALLBACK_DAYS; $attempt++) {
            $url = sprintf(
                '%s/%s/%s/?format=json',
                self::BASE_URL,
                strtolower($currency->value),
                $candidate->format('Y-m-d'),
            );

            $data = $this->request($url);

            if (\is_array($data) && isset($data['rates']) && \is_array($data['rates']) && isset($data['rates'][0])) {
                /** @var array{effectiveDate: string, mid: float|int|string, no: string} $rateData */
                $rateData = $data['rates'][0];

                return NBPRate::create(
                    $currency,
                    BigDecimal::of((string) $rateData['mid']),
                    new \DateTimeImmutable((string) $rateData['effectiveDate'], PolishTimezone::get()),
                    (string) $rateData['no'],
                );
            }

            // No rate for this date — try previous working day
            $candidate = $candidate->modify('-1 day');

            while (! $this->workingDayResolver->isWorkingDay($candidate)) {
                $candidate = $candidate->modify('-1 day');
            }
        }

        throw ExchangeRateNotFoundException::forDate($currency, $targetDate);
    }

    /**
     * HTTP request with retry + exponential backoff.
     * Returns null on 404 (no rate available), throws on other errors.
     *
     * @return array<string, mixed>|null
     */
    private function request(string $url): ?array
    {
        $lastException = null;

        for ($retry = 0; $retry < self::MAX_RETRIES; $retry++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => self::TIMEOUT_SECONDS,
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 404) {
                    return null;
                }

                /** @var array<string, mixed> */
                return $response->toArray();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($retry < self::MAX_RETRIES - 1) {
                    usleep((int) (100_000 * (2 ** $retry))); // 100ms, 200ms, 400ms
                }
            }
        }

        throw new \RuntimeException(
            sprintf('NBP API request failed after %d retries: %s', self::MAX_RETRIES, $url),
            0,
            $lastException,
        );
    }
}
