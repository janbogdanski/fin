<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Policy\TaxRoundingPolicy;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxRoundingPolicyTest extends TestCase
{
    #[DataProvider('roundingProvider')]
    public function testRoundTaxBase(string $input, string $expected): void
    {
        $result = TaxRoundingPolicy::roundTaxBase(BigDecimal::of($input));

        self::assertTrue(
            $result->isEqualTo($expected),
            "Expected {$expected}, got {$result} for input {$input}",
        );
    }

    #[DataProvider('roundingProvider')]
    public function testRoundTax(string $input, string $expected): void
    {
        $result = TaxRoundingPolicy::roundTax(BigDecimal::of($input));

        self::assertTrue(
            $result->isEqualTo($expected),
            "Expected {$expected}, got {$result} for input {$input}",
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function roundingProvider(): iterable
    {
        yield '< 50 groszy -> w dol' => ['100.49', '100'];
        yield '== 50 groszy -> w gore (art. 63 OP)' => ['100.50', '101'];
        yield '> 50 groszy -> w gore' => ['100.51', '101'];
        yield 'zero' => ['0', '0'];
        yield 'negative < 50 groszy' => ['-100.49', '-100'];
        yield 'negative == 50 groszy' => ['-100.50', '-101'];
        yield 'negative > 50 groszy' => ['-100.51', '-101'];
        yield 'already whole number' => ['500', '500'];
        yield '1 grosz' => ['100.01', '100'];
        yield '99 groszy' => ['100.99', '101'];
    }
}
