<?php

declare(strict_types=1);

namespace App\BrokerImport\Infrastructure\Adapter\Revolut;

use App\Shared\Domain\ValueObject\ISIN;

/**
 * Static ticker-to-ISIN mapping for the most common US/EU stocks.
 *
 * Pragmatic first step for Revolut imports which lack ISIN data.
 * P2 backlog: replace with a full lookup service (OpenFIGI, etc.).
 */
final class TickerToISINMap
{
    /**
     * @var array<string, string> ticker => ISIN string
     */
    private const array MAP = [
        // US — Mega Cap
        'AAPL' => 'US0378331005',
        'MSFT' => 'US5949181045',
        'AMZN' => 'US0231351067',
        'GOOG' => 'US02079K3059',
        'GOOGL' => 'US02079K1079',
        'META' => 'US30303M1027',
        'TSLA' => 'US88160R1014',
        'NVDA' => 'US67066G1040',
        'BRK.B' => 'US0846707026',
        'JPM' => 'US46625H1005',
        'V' => 'US92826C8394',
        'JNJ' => 'US4781601046',
        'WMT' => 'US9311421039',
        'PG' => 'US7427181091',
        'MA' => 'US57636Q1040',
        'UNH' => 'US91324P1021',
        'HD' => 'US4370761029',
        'DIS' => 'US2546871060',
        'BAC' => 'US0605051046',
        'XOM' => 'US30231G1022',
        'PFE' => 'US7170811035',
        'KO' => 'US1912161007',
        'PEP' => 'US7134481081',
        'ABBV' => 'US00287Y1091',
        'AVGO' => 'US11135F1012',
        'COST' => 'US22160K1051',
        'TMO' => 'US8835561023',
        'MRK' => 'US58933Y1055',
        'CSCO' => 'US17275R1023',
        'ABT' => 'US0028241000',
        'CRM' => 'US79466L3024',
        'ACN' => 'IE00B4BNMY34',
        'NKE' => 'US6541061031',
        'LLY' => 'US5324571083',
        'ADBE' => 'US00724F1012',
        'AMD' => 'US0079031078',
        'INTC' => 'US4581401001',
        'NFLX' => 'US64110L1061',
        'CMCSA' => 'US20030N1019',
        'ORCL' => 'US68389X1054',
        'TXN' => 'US8825081040',
        'QCOM' => 'US7475251036',
        'T' => 'US00206R1023',
        'VZ' => 'US92343V1044',
        'PYPL' => 'US70450Y1038',
        'UBER' => 'US90353T1007',
        'SQ' => 'US8522341036',
        'SNAP' => 'US83304A1060',
        'PLTR' => 'US69608A1088',
        'COIN' => 'US19260Q1076',
        'SHOP' => 'CA82509L1076',
    ];

    public static function resolve(string $ticker): ?ISIN
    {
        $normalized = strtoupper(trim($ticker));

        if (! isset(self::MAP[$normalized])) {
            return null;
        }

        return ISIN::fromString(self::MAP[$normalized]);
    }

    public static function has(string $ticker): bool
    {
        return isset(self::MAP[strtoupper(trim($ticker))]);
    }
}
