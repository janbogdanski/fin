<?php

declare(strict_types=1);

namespace App\Tests\Unit\ExchangeRate;

use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\ExchangeRate\Infrastructure\Cache\CachedExchangeRateProvider;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedExchangeRateProviderTest extends TestCase
{
    public function testReturnsFromCacheOnHit(): void
    {
        $expectedRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.0512'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );

        $inner = $this->createMock(ExchangeRateProviderInterface::class);
        $inner->expects(self::once())
            ->method('getRateForDate')
            ->willReturn($expectedRate);

        $cache = new ArrayAdapter();
        $provider = new CachedExchangeRateProvider($inner, $cache);

        $transactionDate = new \DateTimeImmutable('2025-03-15');

        // First call — cache miss, delegates to inner
        $rate1 = $provider->getRateForDate(CurrencyCode::USD, $transactionDate);
        // Second call — cache hit, inner NOT called again (expects once)
        $rate2 = $provider->getRateForDate(CurrencyCode::USD, $transactionDate);

        self::assertTrue($rate1->rate()->isEqualTo('4.0512'));
        self::assertTrue($rate2->rate()->isEqualTo('4.0512'));
    }

    public function testDelegatesOnMiss(): void
    {
        $expectedRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.0512'),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );

        $inner = $this->createMock(ExchangeRateProviderInterface::class);
        $inner->expects(self::once())
            ->method('getRateForDate')
            ->with(CurrencyCode::USD, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($expectedRate);

        $cache = new ArrayAdapter();
        $provider = new CachedExchangeRateProvider($inner, $cache);

        $rate = $provider->getRateForDate(CurrencyCode::USD, new \DateTimeImmutable('2025-03-15'));

        self::assertTrue($rate->rate()->isEqualTo('4.0512'));
        self::assertSame('052/A/NBP/2025', $rate->tableNumber());
    }
}
