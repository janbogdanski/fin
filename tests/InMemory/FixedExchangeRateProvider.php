<?php

declare(strict_types=1);

namespace App\Tests\InMemory;

use App\ExchangeRate\Application\Port\ExchangeRateProviderInterface;
use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;

final class FixedExchangeRateProvider implements ExchangeRateProviderInterface
{
    /**
     * @param array<string, string> $ratesByCurrency
     */
    public function __construct(
        private array $ratesByCurrency = [
            'USD' => '4.0000',
            'EUR' => '4.5000',
            'PLN' => '1.0000',
        ],
    ) {
    }

    public function getRateForDate(CurrencyCode $currency, \DateTimeImmutable $transactionDate): NBPRate
    {
        $value = $this->ratesByCurrency[$currency->value] ?? null;
        if ($value === null) {
            throw ExchangeRateNotFoundException::forDate($currency, $transactionDate);
        }

        return NBPRate::create(
            $currency,
            BigDecimal::of($value),
            $transactionDate,
            sprintf('001/A/NBP/%s', $transactionDate->format('Y')),
        );
    }

    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $rates = [];
        $cursor = $from;

        while ($cursor <= $to) {
            $key = sprintf('%s_%s', $currency->value, $cursor->format('Y-m-d'));
            $rates[$key] = $this->getRateForDate($currency, $cursor);
            $cursor = $cursor->modify('+1 day');
        }

        return $rates;
    }
}
