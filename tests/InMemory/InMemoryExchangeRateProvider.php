<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;

/**
 * Deterministic in-memory exchange rate stub for integration tests.
 *
 * Returns fixed rates keyed by currency code so that integration tests
 * do not hit the real NBP API. Rates are intentionally round numbers
 * to make arithmetic assertions straightforward.
 *
 * Default rates (can be overridden per test via setRate()):
 *   USD → 4.0000 PLN
 *   EUR → 4.2000 PLN
 *   GBP → 5.0000 PLN
 */
final class InMemoryExchangeRateProvider implements ExchangeRateProviderInterface
{
    /** @var array<string, BigDecimal> */
    private array $rates;

    private static function defaultRates(): array
    {
        return [
            CurrencyCode::USD->value => BigDecimal::of('4.0000'),
            CurrencyCode::EUR->value => BigDecimal::of('4.2000'),
            CurrencyCode::GBP->value => BigDecimal::of('5.0000'),
        ];
    }

    public function __construct()
    {
        $this->rates = self::defaultRates();
    }

    public function setRate(CurrencyCode $currency, string $rate): void
    {
        $this->rates[$currency->value] = BigDecimal::of($rate);
    }

    public function reset(): void
    {
        $this->rates = self::defaultRates();
    }

    public function getRateForDate(CurrencyCode $currency, \DateTimeImmutable $transactionDate): NBPRate
    {
        if (! isset($this->rates[$currency->value])) {
            throw ExchangeRateNotFoundException::forDate($currency, $transactionDate);
        }

        return NBPRate::create(
            $currency,
            $this->rates[$currency->value],
            $transactionDate->modify('-1 day'),
            '001/A/NBP/2025',
        );
    }

    /**
     * @return array<string, NBPRate>
     */
    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        if (! isset($this->rates[$currency->value])) {
            return [];
        }

        $rate = NBPRate::create(
            $currency,
            $this->rates[$currency->value],
            $from->modify('-1 day'),
            '001/A/NBP/2025',
        );

        return [sprintf('%s_%s', $currency->value, $from->format('Y-m-d')) => $rate];
    }
}
