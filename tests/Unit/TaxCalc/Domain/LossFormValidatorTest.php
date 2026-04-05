<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\TaxCalc\Domain\Service\LossFormValidator;
use App\TaxCalc\Domain\ValueObject\TaxCategory;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class LossFormValidatorTest extends TestCase
{
    public function testCommaAsDecimalSeparatorNormalized(): void
    {
        $result = LossFormValidator::parseAmount('1,5');

        self::assertTrue($result['ok']);
        self::assertInstanceOf(BigDecimal::class, $result['amount']);
        self::assertSame('1.50', (string) $result['amount']);
    }

    public function testAmountAtExactBoundary(): void
    {
        $result = LossFormValidator::parseAmount('100000000');

        self::assertTrue($result['ok']);
        self::assertSame('100000000.00', (string) $result['amount']);
    }

    public function testAmountAboveBoundaryRejected(): void
    {
        $result = LossFormValidator::parseAmount('100000001');

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('error', $result);
    }

    public function testNegativeAmountRejected(): void
    {
        $result = LossFormValidator::parseAmount('-1');

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('error', $result);
    }

    public function testZeroAmountRejected(): void
    {
        $result = LossFormValidator::parseAmount('0');

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('error', $result);
    }

    public function testAlphanumericInputRejected(): void
    {
        $result = LossFormValidator::parseAmount('abc');

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('error', $result);
    }

    public function testTrimmedInput(): void
    {
        $result = LossFormValidator::parseAmount('  5.00  ');

        self::assertTrue($result['ok']);
        self::assertSame('5.00', (string) $result['amount']);
    }

    public function testValidCategoryReturned(): void
    {
        $result = LossFormValidator::parseCategory('EQUITY');

        self::assertSame(TaxCategory::EQUITY, $result);
    }

    public function testUnknownCategoryReturnsNull(): void
    {
        $result = LossFormValidator::parseCategory('unknown');

        self::assertNull($result);
    }
}
