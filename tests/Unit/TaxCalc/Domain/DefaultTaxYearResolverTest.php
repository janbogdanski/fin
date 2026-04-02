<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Service\DefaultTaxYearResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DefaultTaxYearResolverTest extends TestCase
{
    #[DataProvider('taxYearResolutionProvider')]
    public function testResolvesDefaultTaxYear(string $date, int $expectedYear): void
    {
        $clock = new MockClock(new \DateTimeImmutable($date));
        $resolver = new DefaultTaxYearResolver($clock);

        self::assertSame($expectedYear, $resolver->resolve());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function taxYearResolutionProvider(): iterable
    {
        // Before May 1 -> previous year (filing season for prior year)
        yield 'January 1, 2027' => ['2027-01-01', 2026];
        yield 'February 15, 2027' => ['2027-02-15', 2026];
        yield 'March 31, 2027' => ['2027-03-31', 2026];
        yield 'April 30, 2027' => ['2027-04-30', 2026];

        // From May 1 onward -> current year
        yield 'May 1, 2027' => ['2027-05-01', 2027];
        yield 'June 15, 2027' => ['2027-06-15', 2027];
        yield 'December 31, 2027' => ['2027-12-31', 2027];

        // Edge cases around year boundary
        yield 'December 31, 2026' => ['2026-12-31', 2026];
        yield 'January 1, 2026' => ['2026-01-01', 2025];
    }
}
