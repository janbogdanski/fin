<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use App\TaxCalc\Domain\Service\CurrencyConverter;
use App\TaxCalc\Domain\Service\DividendTaxService;
use App\TaxCalc\Domain\Service\UPORegistry;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class DividendTaxServiceTest extends TestCase
{
    private DividendTaxService $service;

    protected function setUp(): void
    {
        $this->service = new DividendTaxService(new UPORegistry(), new CurrencyConverter());
    }

    /**
     * USA 15% WHT (z W-8BEN).
     * $100 * 4.0 PLN/USD = 400 PLN
     * WHT paid: 400 * 15% = 60 PLN
     * Polish tax: 400 * 19% = 76 PLN
     * Due in PL: 76 - 60 = 16 PLN
     */
    public function testUsaDividendWith15PercentWht(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('0.15'),
        );

        self::assertTrue($result->grossDividendPLN->amount()->isEqualTo('400'));
        self::assertTrue($result->whtPaidPLN->amount()->isEqualTo('60'));
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('16'));
        self::assertTrue($result->whtRate->isEqualTo('0.15'));
        self::assertTrue($result->upoRate->isEqualTo('0.15'));
        self::assertTrue($result->sourceCountry->equals(CountryCode::US));
    }

    /**
     * UK 15% WHT (poprawione po review prawnym).
     * GBP 200 * 5.0 PLN/GBP = 1000 PLN
     * WHT: 1000 * 15% = 150 PLN
     * Polish tax: 1000 * 19% = 190 PLN
     * Due: 190 - 150 = 40 PLN
     */
    public function testUkDividendWith15PercentWht(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('200', CurrencyCode::GBP),
            nbpRate: $this->nbpRate(CurrencyCode::GBP, '5.0'),
            sourceCountry: CountryCode::GB,
            actualWHTRate: BigDecimal::of('0.15'),
        );

        self::assertTrue($result->grossDividendPLN->amount()->isEqualTo('1000'));
        self::assertTrue($result->whtPaidPLN->amount()->isEqualTo('150'));
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('40'));
        self::assertTrue($result->upoRate->isEqualTo('0.15'));
    }

    /**
     * WHT > UPO rate — odliczenie cappowane do UPO.
     * Np. USA bez W-8BEN: 30% WHT, UPO 15%.
     * $100 * 4.0 = 400 PLN
     * effectiveWHT = min(30%, 15%) = 15%
     * WHT deduction: 400 * 15% = 60 PLN
     * Polish tax: 400 * 19% = 76 PLN
     * Due: 76 - 60 = 16 PLN
     * (whtPaidPLN nadal raportuje faktycznie zaplacone 120 PLN)
     */
    public function testWhtExceedsUpoRateDeductionIsCapped(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('0.30'),
        );

        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('16'));
        self::assertTrue($result->whtPaidPLN->amount()->isEqualTo('120'));
    }

    /**
     * Dywidenda w PLN — brak przeliczenia walut.
     * Np. polska spolka wyplacajaca dywidende (hipotetyczny scenariusz z PLN).
     */
    public function testPlnDividendNoConversion(): void
    {
        // PLN dividend — NBPRate jest wymagany ale CurrencyConverter::toPLN
        // zwraca Money as-is dla PLN.
        // Musimy podac NBPRate z dowolna waluta, bo CurrencyConverter::toPLN
        // pomija rate dla PLN.
        $nbpRate = $this->nbpRate(CurrencyCode::USD, '4.0');

        $result = $this->service->calculate(
            grossDividend: Money::of('1000', CurrencyCode::PLN),
            nbpRate: $nbpRate,
            sourceCountry: CountryCode::PL,
            actualWHTRate: BigDecimal::of('0.19'),
        );

        self::assertTrue($result->grossDividendPLN->amount()->isEqualTo('1000'));
        // WHT 19% of 1000 = 190, Polish tax 19% of 1000 = 190, due = 0
        self::assertTrue($result->polishTaxDue->isZero());
    }

    /**
     * Kraj bez UPO — UPO rate powinien byc 19%.
     */
    public function testCountryWithoutUpoReturnsDefaultRate(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::HKD),
            nbpRate: $this->nbpRate(CurrencyCode::HKD, '0.50'),
            sourceCountry: CountryCode::HK,
            actualWHTRate: BigDecimal::of('0'),
        );

        self::assertTrue($result->upoRate->isEqualTo('0.19'));
        // No WHT paid, full 19% due: 50 * 0.19 = 9.5
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('9.50'));
    }

    /**
     * P0-001: WHT 30% (USA bez W-8BEN) powinien byc cappowany do UPO rate 15%.
     *
     * $100 * 4.0 = 400 PLN
     * effectiveWHT = min(30%, 15%) = 15%
     * WHT deduction: 400 * 15% = 60 PLN
     * Polish tax: 400 * 19% = 76 PLN
     * Due: 76 - 60 = 16 PLN (NIE 0, bo nadplata WHT nie zmniejsza PL podatku)
     */
    public function testWhtIsCappedToUpoRate(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('0.30'),
        );

        // Odliczenie cappowane do UPO 15%, wiec taxDue = 76 - 60 = 16
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('16'));
        // WHT faktycznie zaplacony (30%) nadal raportowany poprawnie
        self::assertTrue($result->whtPaidPLN->amount()->isEqualTo('120'));
        self::assertTrue($result->whtRate->isEqualTo('0.30'));
        self::assertTrue($result->upoRate->isEqualTo('0.15'));
    }

    /**
     * Gdy WHT <= UPO rate, odliczenie = full WHT (bez cappowania).
     * USA z W-8BEN: 15% WHT, UPO 15% -> effectiveWHT = 15%.
     */
    public function testWhtBelowUpoRateIsNotCapped(): void
    {
        $result = $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('0.10'),
        );

        // effectiveWHT = min(10%, 15%) = 10%
        // WHT deduction: 400 * 10% = 40 PLN
        // Polish tax: 400 * 19% = 76 PLN
        // Due: 76 - 40 = 36
        self::assertTrue($result->polishTaxDue->amount()->isEqualTo('36'));
    }

    public function testRejectsNegativeWhtRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('-0.01'),
        );
    }

    public function testRejectsWhtRateOver100Percent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculate(
            grossDividend: Money::of('100', CurrencyCode::USD),
            nbpRate: $this->nbpRate(CurrencyCode::USD, '4.0'),
            sourceCountry: CountryCode::US,
            actualWHTRate: BigDecimal::of('1.01'),
        );
    }

    private function nbpRate(CurrencyCode $currency, string $rate): NBPRate
    {
        return NBPRate::create(
            $currency,
            BigDecimal::of($rate),
            new \DateTimeImmutable('2025-03-14'),
            '052/A/NBP/2025',
        );
    }
}
