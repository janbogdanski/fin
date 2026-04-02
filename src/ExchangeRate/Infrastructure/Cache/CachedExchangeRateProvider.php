<?php

declare(strict_types=1);

namespace App\ExchangeRate\Infrastructure\Cache;

use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class CachedExchangeRateProvider implements ExchangeRateProviderInterface
{
    private const int TTL_SECONDS = 30 * 24 * 60 * 60; // 30 days

    private const string KEY_PREFIX = 'nbp_rate';

    public function __construct(
        private ExchangeRateProviderInterface $inner,
        private CacheInterface $cache,
    ) {
    }

    public function getRateForDate(CurrencyCode $currency, \DateTimeImmutable $transactionDate): NBPRate
    {
        $cacheKey = self::buildKey($currency, $transactionDate);

        /** @var NBPRate */
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currency, $transactionDate): NBPRate {
            $item->expiresAfter(self::TTL_SECONDS);

            return $this->inner->getRateForDate($currency, $transactionDate);
        });
    }

    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        // Range requests are not individually cached — delegate directly.
        // Individual rates from the range can be cached via getRateForDate later.
        return $this->inner->getRatesForDateRange($currency, $from, $to);
    }

    private static function buildKey(CurrencyCode $currency, \DateTimeImmutable $date): string
    {
        return sprintf('%s_%s_%s', self::KEY_PREFIX, $currency->value, $date->format('Y-m-d'));
    }
}
