<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Infrastructure\Adapter\Revolut\TickerToISINMap;
use PHPUnit\Framework\TestCase;

final class TickerToISINMapTest extends TestCase
{
    /**
     * @dataProvider knownTickerProvider
     */
    public function testResolvesKnownTickers(string $ticker, string $expectedIsin): void
    {
        $isin = TickerToISINMap::resolve($ticker);

        self::assertNotNull($isin, "Ticker '{$ticker}' should resolve to an ISIN");
        self::assertSame($expectedIsin, $isin->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownTickerProvider(): iterable
    {
        yield 'AAPL' => ['AAPL', 'US0378331005'];
        yield 'MSFT' => ['MSFT', 'US5949181045'];
        yield 'TSLA' => ['TSLA', 'US88160R1014'];
        yield 'AMZN' => ['AMZN', 'US0231351067'];
        yield 'GOOG' => ['GOOG', 'US02079K3059'];
        yield 'META' => ['META', 'US30303M1027'];
        yield 'NVDA' => ['NVDA', 'US67066G1040'];
    }

    public function testReturnsNullForUnknownTicker(): void
    {
        self::assertNull(TickerToISINMap::resolve('XYZNOTREAL'));
    }

    public function testIsCaseInsensitive(): void
    {
        $upper = TickerToISINMap::resolve('AAPL');
        $lower = TickerToISINMap::resolve('aapl');
        $mixed = TickerToISINMap::resolve('Aapl');

        self::assertNotNull($upper);
        self::assertNotNull($lower);
        self::assertNotNull($mixed);
        self::assertSame($upper->toString(), $lower->toString());
        self::assertSame($upper->toString(), $mixed->toString());
    }

    public function testTrimsWhitespace(): void
    {
        $isin = TickerToISINMap::resolve('  AAPL  ');

        self::assertNotNull($isin);
        self::assertSame('US0378331005', $isin->toString());
    }

    public function testHasReturnsTrueForKnownTicker(): void
    {
        self::assertTrue(TickerToISINMap::has('AAPL'));
        self::assertTrue(TickerToISINMap::has('msft'));
    }

    public function testHasReturnsFalseForUnknownTicker(): void
    {
        self::assertFalse(TickerToISINMap::has('XYZNOTREAL'));
    }

    public function testAllMappedISINsAreValid(): void
    {
        // Verify that every ISIN in the map passes ISIN validation (Luhn check)
        $reflection = new \ReflectionClass(TickerToISINMap::class);
        $constants = $reflection->getConstants();
        $map = $constants['MAP'];

        self::assertIsArray($map);
        self::assertNotEmpty($map);

        foreach ($map as $ticker => $isinString) {
            $isin = TickerToISINMap::resolve($ticker);
            self::assertNotNull($isin, "Ticker '{$ticker}' should resolve");
            self::assertSame($isinString, $isin->toString(), "ISIN for '{$ticker}' must match map");
        }
    }
}
