<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\ValueObject\ISIN;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ISINTest extends TestCase
{
    public function testValidIsinApple(): void
    {
        $isin = ISIN::fromString('US0378331005');

        self::assertSame('US0378331005', $isin->toString());
        self::assertSame('US', $isin->countryCode());
    }

    public function testNormalizesToUppercase(): void
    {
        $isin = ISIN::fromString('us0378331005');

        self::assertSame('US0378331005', $isin->toString());
    }

    public function testTrimsWhitespace(): void
    {
        $isin = ISIN::fromString('  US0378331005  ');

        self::assertSame('US0378331005', $isin->toString());
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ISIN::fromString('INVALID');
    }

    public function testRejectsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ISIN::fromString('US037833100');
    }

    public function testRejectsInvalidCheckDigit(): void
    {
        // US0378331005 is valid, US0378331009 has wrong check digit
        $this->expectException(\InvalidArgumentException::class);
        ISIN::fromString('US0378331009');
    }

    public function testEquals(): void
    {
        $a = ISIN::fromString('US0378331005');
        $b = ISIN::fromString('US0378331005');
        $c = ISIN::fromString('IE00B4L5Y983'); // VWCE

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    /**
     * Known valid ISINs
     */
    /**
     * @return array<string, array{string}>
     */
    public static function validISINProvider(): array
    {
        return [
            'Apple' => ['US0378331005'],
            'VWCE ETF' => ['IE00B4L5Y983'],
            'CD Projekt' => ['PLOPTTC00011'],
            'Samsung' => ['KR7005930003'],
            'Nestle' => ['CH0038863350'],
        ];
    }

    #[DataProvider('validISINProvider')]
    public function testValidIsins(string $isin): void
    {
        $vo = ISIN::fromString($isin);
        self::assertSame($isin, $vo->toString());
    }

    // --- fromUnchecked ---

    public function testFromUncheckedAcceptsTickerSymbol(): void
    {
        $vo = ISIN::fromUnchecked('AAPL.US');
        self::assertSame('AAPL.US', $vo->toString());
    }

    public function testFromUncheckedTrimsWhitespace(): void
    {
        $vo = ISIN::fromUnchecked('  AAPL.US  ');
        self::assertSame('AAPL.US', $vo->toString());
    }

    public function testFromUncheckedRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ISIN::fromUnchecked('');
    }

    public function testFromUncheckedRejectsWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ISIN::fromUnchecked('   ');
    }

    public function testFromUncheckedDoesNotValidateFormat(): void
    {
        // A real broker symbol that would fail ISIN format/Luhn checks must pass fromUnchecked.
        $vo = ISIN::fromUnchecked('INVALID-TICKER/XTB');
        self::assertSame('INVALID-TICKER/XTB', $vo->toString());
    }
}
