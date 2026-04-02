<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Service;

use App\Shared\Domain\ValueObject\CountryCode;
use Brick\Math\BigDecimal;

/**
 * Rejestr stawek WHT z umow o unikaniu podwojnego opodatkowania (UPO)
 * zawartych przez Polske.
 *
 * Stawki dotycza dywidend wyplacanych osobom fizycznym (rezydentom PL).
 * Warunek: posiadanie certyfikatu rezydencji + formularza (np. W-8BEN dla USA).
 *
 * Jezeli kraj NIE ma UPO z Polska — default 19% (pelny polski podatek,
 * bez mozliwosci odliczenia podatku zaplaconego za granica).
 *
 * @see art. 30a ust. 2 ustawy o PIT (odliczenie podatku zagranicznego)
 * @see art. 27 ust. 9 ustawy o PIT (metoda proporcjonalnego odliczenia)
 */
final readonly class UPORegistry
{
    /**
     * @var array<string, string> Kraj -> stawka WHT z UPO (jako string dla BigDecimal)
     */
    private const array RATES = [
        'US' => '0.15',  // z W-8BEN; bez formularza: 30%
        'GB' => '0.15',  // poprawione po review prawnym — NIE 10%
        'DE' => '0.15',
        'IE' => '0.15',
        'NL' => '0.15',
        'CH' => '0.15',
        'CA' => '0.15',
        'JP' => '0.10',
        'AU' => '0.15',
        'LU' => '0.15',
        'FR' => '0.15',
        'SE' => '0.15',
        'NO' => '0.15',
        'DK' => '0.15',
        'FI' => '0.15',
    ];

    /**
     * Stawka WHT z UPO dla danego kraju.
     * Jezeli brak umowy — zwraca 19% (brak prawa do odliczenia = pelny PIT).
     */
    private const string DEFAULT_RATE = '0.19';

    public function getRate(CountryCode $country): BigDecimal
    {
        $rate = self::RATES[$country->value] ?? self::DEFAULT_RATE;

        return BigDecimal::of($rate);
    }

    /**
     * Czy Polska ma UPO z danym krajem.
     */
    public function hasAgreement(CountryCode $country): bool
    {
        return isset(self::RATES[$country->value]);
    }
}
