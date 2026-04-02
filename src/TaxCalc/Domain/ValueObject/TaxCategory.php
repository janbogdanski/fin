<?php

declare(strict_types=1);

namespace App\TaxCalc\Domain\ValueObject;

/**
 * Kategoria podatkowa instrumentu.
 * Determinuje koszyk w PIT-38 i zasady łączenia zysków/strat.
 */
enum TaxCategory: string
{
    /** Akcje, ETF, obligacje — sekcja C PIT-38 */
    case EQUITY = 'EQUITY';

    /** CFD, opcje, warranty, futures — sekcja C PIT-38 (łączy się z EQUITY) */
    case DERIVATIVE = 'DERIVATIVE';

    /** Kryptowaluty — OSOBNY koszyk w PIT-38 (art. 30b ust. 5a-5g) */
    case CRYPTO = 'CRYPTO';
}
