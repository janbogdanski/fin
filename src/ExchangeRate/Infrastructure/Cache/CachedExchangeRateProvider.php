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
        // First, try a lookup key based on transactionDate (may hit if previously stored)
        $lookupKey = self::buildLookupKey($currency, $transactionDate);

        /** @var NBPRate */
        return $this->cache->get($lookupKey, function (ItemInterface $item) use ($currency, $transactionDate): NBPRate {
            $item->expiresAfter(self::TTL_SECONDS);

            $rate = $this->inner->getRateForDate($currency, $transactionDate);

            // Also cache by the actual effectiveDate for deduplication
            $effectiveKey = self::buildEffectiveKey($currency, $rate->effectiveDate());
            $this->cache->get($effectiveKey, function (ItemInterface $effectiveItem) use ($rate): NBPRate {
                $effectiveItem->expiresAfter(self::TTL_SECONDS);

                return $rate;
            });

            return $rate;
        });
    }

    /**
     * @return array<string, NBPRate>
     */
    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $rates = $this->inner->getRatesForDateRange($currency, $from, $to);

        // Cache each individual rate by effectiveDate for subsequent getRateForDate hits
        foreach ($rates as $rate) {
            $effectiveKey = self::buildEffectiveKey($currency, $rate->effectiveDate());
            $this->cache->get($effectiveKey, function (ItemInterface $item) use ($rate): NBPRate {
                $item->expiresAfter(self::TTL_SECONDS);

                return $rate;
            });
        }

        return $rates;
    }

    private static function buildLookupKey(CurrencyCode $currency, \DateTimeImmutable $date): string
    {
        return sprintf('%s_lookup_%s_%s', self::KEY_PREFIX, $currency->value, $date->format('Y-m-d'));
    }

    private static function buildEffectiveKey(CurrencyCode $currency, \DateTimeImmutable $date): string
    {
        return sprintf('%s_effective_%s_%s', self::KEY_PREFIX, $currency->value, $date->format('Y-m-d'));
    }
}
