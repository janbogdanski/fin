<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\ValueObject\TaxYear;
use PHPUnit\Framework\TestCase;

final class TaxYearTest extends TestCase
{
    public function testCreatesValidTaxYear(): void
    {
        $year = TaxYear::of(2025);

        self::assertSame(2025, $year->value);
    }

    public function testAcceptsLowerBoundary(): void
    {
        $year = TaxYear::of(2000);

        self::assertSame(2000, $year->value);
    }

    public function testAcceptsUpperBoundary(): void
    {
        $year = TaxYear::of(2100);

        self::assertSame(2100, $year->value);
    }

    public function testRejectsYearBelow2000(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax year must be between 2000 and 2100');

        TaxYear::of(1999);
    }

    public function testRejectsYearAbove2100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax year must be between 2000 and 2100');

        TaxYear::of(2101);
    }

    public function testEquals(): void
    {
        $year1 = TaxYear::of(2025);
        $year2 = TaxYear::of(2025);
        $year3 = TaxYear::of(2026);

        self::assertTrue($year1->equals($year2));
        self::assertFalse($year1->equals($year3));
    }
}
