<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\Policy;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Zaokraglanie podatkowe wg art. 63 ss 1 Ordynacji podatkowej.
 *
 * "Podstawy opodatkowania [...] zaokragla sie do pelnych zlotych
 *  w ten sposob, ze koncowki kwot wynoszace mniej niz 50 groszy
 *  pomija sie, a koncowki kwot wynoszace 50 i wiecej groszy podwyzsza sie
 *  do pelnych zlotych."
 *
 * UWAGA: To jest zaokraglanie MATEMATYCZNE (>= 0.50 w gore), NIE obcinanie.
 *
 * @see art. 63 ss 1 ustawy z dnia 29 sierpnia 1997 r. Ordynacja podatkowa
 */
final class TaxRoundingPolicy
{
    private function __construct()
    {
    }

    /**
     * Zaokragla podstawe opodatkowania do pelnych zlotych.
     */
    public static function roundTaxBase(BigDecimal $amount): BigDecimal
    {
        return self::roundToFullZloty($amount);
    }

    /**
     * Zaokragla kwote podatku do pelnych zlotych.
     */
    public static function roundTax(BigDecimal $amount): BigDecimal
    {
        return self::roundToFullZloty($amount);
    }

    /**
     * Art. 63 ss 1 OP: >= 50 groszy -> w gore, < 50 groszy -> w dol.
     * To odpowiada RoundingMode::HALF_UP na scale 0.
     */
    private static function roundToFullZloty(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(0, RoundingMode::HALF_UP);
    }
}
