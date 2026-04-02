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
 * Rates are loaded from config/upo_rates.yaml via Symfony parameter bag.
 *
 * @see art. 30a ust. 2 ustawy o PIT (odliczenie podatku zagranicznego)
 * @see art. 27 ust. 9 ustawy o PIT (metoda proporcjonalnego odliczenia)
 */
final readonly class UPORegistry
{
    /**
     * Fallback rates — used when no config is injected (backward compatibility, tests).
     *
     * @var array<string, string> Kraj -> stawka WHT z UPO (jako string dla BigDecimal)
     */
    private const array DEFAULT_RATES = [
        'US' => '0.15',
        'GB' => '0.15',
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

    private const string FALLBACK_DEFAULT_RATE = '0.19';

    /**
     * @var array<string, string>
     */
    private array $rates;

    private string $defaultRate;

    /**
     * @param array<string, string>|null $rates       WHT rates by country code (from config/upo_rates.yaml)
     * @param string|null                $defaultRate  Default rate for countries without UPO
     */
    public function __construct(
        ?array $rates = null,
        ?string $defaultRate = null,
    ) {
        $this->rates = $rates ?? self::DEFAULT_RATES;
        $this->defaultRate = $defaultRate ?? self::FALLBACK_DEFAULT_RATE;
    }

    /**
     * Stawka WHT z UPO dla danego kraju.
     * Jezeli brak umowy — zwraca default rate (brak prawa do odliczenia = pelny PIT).
     */
    public function getRate(CountryCode $country): BigDecimal
    {
        $rate = $this->rates[$country->value] ?? $this->defaultRate;

        return BigDecimal::of($rate);
    }

    /**
     * Czy Polska ma UPO z danym krajem.
     */
    public function hasAgreement(CountryCode $country): bool
    {
        return isset($this->rates[$country->value]);
    }
}
