<?php

declare(strict_types=1);

namespace App\Tests\GoldenDataset;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\Shared\Domain\ValueObject\UserId;
use App\TaxCalc\Domain\Model\AnnualTaxCalculation;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\DividendTaxService;
use App\TaxCalc\Domain\Service\UPORegistry;
use App\TaxCalc\Domain\ValueObject\TaxYear;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Golden Dataset #004 -- Foreign dividends with UPO cap (US + Germany).
 *
 * Scenario A: US dividend
 *   Gross: $500, actual WHT rate: 30% ($150 paid), NBP 4.00
 *   grossPLN = 500 * 4.00 = 2000.00 PLN
 *   whtPaidPLN = 2000 * 0.30 = 600.00 PLN
 *   UPO rate US = 15% -> effectiveWHT capped to 15%
 *   whtDeduction = 2000 * 0.15 = 300.00 PLN
 *   polishTax = 2000 * 0.19 = 380.00 PLN
 *   taxDuePL = 380.00 - 300.00 = 80.00 PLN
 *
 * Scenario B: DE dividend
 *   Gross: EUR 300, actual WHT rate: 26.375% (EUR 79.125 paid), NBP 4.50
 *   grossPLN = 300 * 4.50 = 1350.00 PLN
 *   whtPaidPLN = 1350 * 0.26375 = 356.0625 PLN
 *   UPO rate DE = 15% -> effectiveWHT capped to 15%
 *   whtDeduction = 1350 * 0.15 = 202.50 PLN
 *   polishTax = 1350 * 0.19 = 256.50 PLN
 *   taxDuePL = 256.50 - 202.50 = 54.00 PLN
 *
 * Total dividend tax due = 80.00 + 54.00 = 134.00 PLN
 *
 * @see art. 30a ust. 2 ustawy o PIT -- odliczenie WHT capped to UPO rate
 */
final class GoldenDataset004ForeignDividendsUPOCapTest extends TestCase
{
    public function testForeignDividendsWithUPOCap(): void
    {
        // ====================================================================
        // STEP 1: Calculate dividend tax for US dividend
        // ====================================================================

        $upoRegistry = new UPORegistry(); // default rates: US=15%, DE=15%
        $converter = new CurrencyConverter();
        $dividendService = new DividendTaxService($upoRegistry, $converter);

        $usRate = NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.00'),
            new \DateTimeImmutable('2025-06-13'),
            '114/A/NBP/2025',
        );

        $usResult = $dividendService->calculate(
            Money::of('500.00', CurrencyCode::USD),
            $usRate,
            CountryCode::US,
            BigDecimal::of('0.30'), // actual WHT 30%
        );

        // --- US dividend verification ---

        // grossDividendPLN = 500 * 4.00 = 2000.00
        self::assertTrue(
            $usResult->grossDividendPLN->amount()->isEqualTo('2000.00'),
            "US grossPLN: 500 * 4.00 = 2000.00, got: {$usResult->grossDividendPLN->amount()}",
        );

        // whtPaidPLN = 2000 * 0.30 = 600.00
        self::assertTrue(
            $usResult->whtPaidPLN->amount()->isEqualTo('600.00'),
            "US whtPaidPLN: 2000 * 0.30 = 600.00, got: {$usResult->whtPaidPLN->amount()}",
        );

        // UPO rate = 0.15
        self::assertTrue(
            $usResult->upoRate->isEqualTo('0.15'),
            "US UPO rate should be 0.15, got: {$usResult->upoRate}",
        );

        // polishTaxDue = 2000 * 0.19 - 2000 * 0.15 = 380 - 300 = 80.00
        self::assertTrue(
            $usResult->polishTaxDue->amount()->isEqualTo('80.00'),
            "US taxDue: 380 - 300 = 80.00, got: {$usResult->polishTaxDue->amount()}",
        );

        // ====================================================================
        // STEP 2: Calculate dividend tax for DE dividend
        // ====================================================================

        $deRate = NBPRate::create(
            CurrencyCode::EUR,
            BigDecimal::of('4.50'),
            new \DateTimeImmutable('2025-08-08'),
            '155/A/NBP/2025',
        );

        $deResult = $dividendService->calculate(
            Money::of('300.00', CurrencyCode::EUR),
            $deRate,
            CountryCode::DE,
            BigDecimal::of('0.26375'), // German WHT 26.375%
        );

        // --- DE dividend verification ---

        // grossDividendPLN = 300 * 4.50 = 1350.00
        self::assertTrue(
            $deResult->grossDividendPLN->amount()->isEqualTo('1350.00'),
            "DE grossPLN: 300 * 4.50 = 1350.00, got: {$deResult->grossDividendPLN->amount()}",
        );

        // whtPaidPLN = 1350 * 0.26375 = 356.0625
        self::assertTrue(
            $deResult->whtPaidPLN->amount()->isEqualTo('356.0625'),
            "DE whtPaidPLN: 1350 * 0.26375 = 356.0625, got: {$deResult->whtPaidPLN->amount()}",
        );

        // UPO rate = 0.15
        self::assertTrue(
            $deResult->upoRate->isEqualTo('0.15'),
            "DE UPO rate should be 0.15, got: {$deResult->upoRate}",
        );

        // polishTaxDue = 1350 * 0.19 - 1350 * 0.15 = 256.50 - 202.50 = 54.00
        self::assertTrue(
            $deResult->polishTaxDue->amount()->isEqualTo('54.00'),
            "DE taxDue: 256.50 - 202.50 = 54.00, got: {$deResult->polishTaxDue->amount()}",
        );

        // ====================================================================
        // STEP 3: AnnualTaxCalculation -- aggregate both dividends
        // ====================================================================

        $userId = UserId::generate();
        $calc = AnnualTaxCalculation::create($userId, TaxYear::of(2025));
        $calc->addDividendResult($usResult);
        $calc->addDividendResult($deResult);
        $calc->finalize();

        // dividendsByCountry should have 2 entries
        $byCountry = $calc->dividendsByCountry();
        self::assertCount(2, $byCountry, 'Should have 2 country entries (US, DE)');
        self::assertArrayHasKey('US', $byCountry);
        self::assertArrayHasKey('DE', $byCountry);

        // US summary
        $usSummary = $byCountry['US'];
        self::assertTrue(
            $usSummary->grossDividendPLN->isEqualTo('2000.00'),
            "US gross in summary: 2000.00, got: {$usSummary->grossDividendPLN}",
        );
        self::assertTrue(
            $usSummary->polishTaxDue->isEqualTo('80.00'),
            "US taxDue in summary: 80.00, got: {$usSummary->polishTaxDue}",
        );

        // DE summary
        $deSummary = $byCountry['DE'];
        self::assertTrue(
            $deSummary->grossDividendPLN->isEqualTo('1350.00'),
            "DE gross in summary: 1350.00, got: {$deSummary->grossDividendPLN}",
        );
        self::assertTrue(
            $deSummary->polishTaxDue->isEqualTo('54.00'),
            "DE taxDue in summary: 54.00, got: {$deSummary->polishTaxDue}",
        );

        // Total dividend tax due = 80.00 + 54.00 = 134.00
        self::assertTrue(
            $calc->dividendTotalTaxDue()->isEqualTo('134.00'),
            "dividendTotalTaxDue: 80 + 54 = 134.00, got: {$calc->dividendTotalTaxDue()}",
        );

        // Total tax (no equity, no crypto) = 134.00
        self::assertTrue(
            $calc->totalTaxDue()->isEqualTo('134.00'),
            "totalTaxDue: 0 + 134 + 0 = 134.00, got: {$calc->totalTaxDue()}",
        );
    }
}
