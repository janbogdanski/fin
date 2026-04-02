<?php

declare(strict_types=1);

namespace App\ExchangeRate\Application\Port;

use App\ExchangeRate\Domain\Exception\ExchangeRateNotFoundException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\NBPRate;

interface ExchangeRateProviderInterface
{
    /**
     * Pobiera kurs NBP z ostatniego dnia roboczego PRZED podaną datą.
     * Art. 11a ust. 1 ustawy o PIT.
     *
     * @throws ExchangeRateNotFoundException gdy kurs niedostępny
     */
    public function getRateForDate(CurrencyCode $currency, \DateTimeImmutable $transactionDate): NBPRate;

    /**
     * Batch: pobiera kursy dla zakresu dat (optymalizacja — jedno zapytanie do API).
     *
     * @return array<string, NBPRate> key = "USD_2025-03-14"
     */
    public function getRatesForDateRange(
        CurrencyCode $currency,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array;
}
