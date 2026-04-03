<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\CountryCode;
use App\TaxCalc\Domain\Model\DividendCountrySummary;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for DividendCountrySummary::add().
 * Targets: effectiveWHTRate ternary, dividedBy scale, zero-gross edge case.
 */
final class DividendCountrySummaryMutationTest extends TestCase
{
    /**
     * Kills ternary swap: when merged gross is zero, effectiveWHTRate must be zero.
     */
    public function testAddWithZeroGrossReturnsZeroEffectiveRate(): void
    {
        $summary1 = new DividendCountrySummary(
            country: CountryCode::US,
            grossDividendPLN: BigDecimal::zero(),
            whtPaidPLN: BigDecimal::zero(),
            polishTaxDue: BigDecimal::zero(),
            effectiveWHTRate: BigDecimal::zero(),
        );

        $summary2 = new DividendCountrySummary(
            country: CountryCode::US,
            grossDividendPLN: BigDecimal::zero(),
            whtPaidPLN: BigDecimal::zero(),
            polishTaxDue: BigDecimal::zero(),
            effectiveWHTRate: BigDecimal::zero(),
        );

        $merged = $summary1->add($summary2);

        self::assertTrue($merged->effectiveWHTRate->isZero());
    }

    /**
     * Kills dividedBy scale mutant: effectiveWHTRate must have scale 6.
     */
    public function testEffectiveWHTRateHasScale6AfterAdd(): void
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

        self::assertSame(6, $merged->effectiveWHTRate->getScale());
        // 45/300 = 0.150000
        self::assertTrue($merged->effectiveWHTRate->isEqualTo('0.150000'));
    }

    /**
     * Verifies polishTaxDue is correctly summed.
     */
    public function testPolishTaxDueSummedCorrectly(): void
    {
        $summary1 = new DividendCountrySummary(
            country: CountryCode::DE,
            grossDividendPLN: BigDecimal::of('50.00'),
            whtPaidPLN: BigDecimal::of('13.25'),
            polishTaxDue: BigDecimal::of('0.00'),
            effectiveWHTRate: BigDecimal::of('0.265000'),
        );

        $summary2 = new DividendCountrySummary(
            country: CountryCode::DE,
            grossDividendPLN: BigDecimal::of('100.00'),
            whtPaidPLN: BigDecimal::of('15.00'),
            polishTaxDue: BigDecimal::of('4.00'),
            effectiveWHTRate: BigDecimal::of('0.150000'),
        );

        $merged = $summary1->add($summary2);

        self::assertTrue($merged->polishTaxDue->isEqualTo('4.00'));
        self::assertTrue($merged->grossDividendPLN->isEqualTo('150.00'));
        self::assertTrue($merged->whtPaidPLN->isEqualTo('28.25'));
    }
}
