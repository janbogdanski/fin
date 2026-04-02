<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\CountryCode;
use App\TaxCalc\Domain\Model\DividendCountrySummary;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class DividendCountrySummaryTest extends TestCase
{
    /**
     * P3-007: add() with different countries must throw InvalidArgumentException.
     * Merging dividend summaries from US and DE makes no sense — they are separate PIT-ZG lines.
     */
    public function testAddWithDifferentCountriesThrowsInvalidArgumentException(): void
    {
        $usSummary = new DividendCountrySummary(
            country: CountryCode::US,
            grossDividendPLN: BigDecimal::of('100.00'),
            whtPaidPLN: BigDecimal::of('15.00'),
            polishTaxDue: BigDecimal::of('4.00'),
            effectiveWHTRate: BigDecimal::of('0.150000'),
        );

        $deSummary = new DividendCountrySummary(
            country: CountryCode::DE,
            grossDividendPLN: BigDecimal::of('50.00'),
            whtPaidPLN: BigDecimal::of('13.25'),
            polishTaxDue: BigDecimal::of('0.00'),
            effectiveWHTRate: BigDecimal::of('0.265000'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('different countries');

        $usSummary->add($deSummary);
    }

    /**
     * add() with same country merges correctly.
     */
    public function testAddWithSameCountryMerges(): void
    {
        $summary1 = new DividendCountrySummary(
            country: CountryCode::US,
            grossDividendPLN: BigDecimal::of('100.00'),
            whtPaidPLN: BigDecimal::of('15.00'),
            polishTaxDue: BigDecimal::of('4.00'),
            effectiveWHTRate: BigDecimal::of('0.150000'),
        );

        $summary2 = new DividendCountrySummary(
            country: CountryCode::US,
            grossDividendPLN: BigDecimal::of('200.00'),
            whtPaidPLN: BigDecimal::of('30.00'),
            polishTaxDue: BigDecimal::of('8.00'),
            effectiveWHTRate: BigDecimal::of('0.150000'),
        );

        $merged = $summary1->add($summary2);

        self::assertTrue($merged->grossDividendPLN->isEqualTo('300.00'));
        self::assertTrue($merged->whtPaidPLN->isEqualTo('45.00'));
        self::assertTrue($merged->polishTaxDue->isEqualTo('12.00'));
        self::assertSame(CountryCode::US, $merged->country);
    }
}
