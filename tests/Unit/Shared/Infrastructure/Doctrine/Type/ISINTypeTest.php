<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Infrastructure\Doctrine\Type\ISINType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

final class ISINTypeTest extends TestCase
{
    private ISINType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        if (! Type::hasType(ISINType::NAME)) {
            Type::addType(ISINType::NAME, ISINType::class);
        }

        /** @var ISINType $type */
        $type = Type::getType(ISINType::NAME);
        $this->type = $type;
        $this->platform = new PostgreSQLPlatform();
    }

    // --- convertToPHPValue ---

    public function testConvertsNullToNull(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertsValidIsinStringToIsinValueObject(): void
    {
        $isin = $this->type->convertToPHPValue('US0378331005', $this->platform);

        self::assertInstanceOf(ISIN::class, $isin);
        self::assertSame('US0378331005', $isin->toString());
    }

    public function testConvertsXtbTickerSymbolWithoutThrowingValidationError(): void
    {
        // XTB does not provide ISINs — it stores ticker symbols like "AAPL.US"
        // The type must use fromUnchecked so hydration does not throw.
        $isin = $this->type->convertToPHPValue('AAPL.US', $this->platform);

        self::assertInstanceOf(ISIN::class, $isin);
        self::assertSame('AAPL.US', $isin->toString());
    }

    public function testConvertsLongBrokerSymbolWithoutThrowingValidationError(): void
    {
        $isin = $this->type->convertToPHPValue('VWCE.DE', $this->platform);

        self::assertInstanceOf(ISIN::class, $isin);
        self::assertSame('VWCE.DE', $isin->toString());
    }

    // --- convertToDatabaseValue ---

    public function testConvertsNullToDatabaseNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertsValidIsinInstanceToString(): void
    {
        $isin = ISIN::fromString('US0378331005');
        $result = $this->type->convertToDatabaseValue($isin, $this->platform);

        self::assertSame('US0378331005', $result);
    }

    public function testConvertsUncheckedIsinInstanceToString(): void
    {
        $isin = ISIN::fromUnchecked('AAPL.US');
        $result = $this->type->convertToDatabaseValue($isin, $this->platform);

        self::assertSame('AAPL.US', $result);
    }

    public function testThrowsWhenDatabaseValueIsNotIsinInstance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->type->convertToDatabaseValue('not-an-isin-object', $this->platform);
    }

    // --- round-trip ---

    public function testRoundTripForStandardIsin(): void
    {
        $original = ISIN::fromString('IE00B4L5Y983');
        $dbValue = $this->type->convertToDatabaseValue($original, $this->platform);
        $hydrated = $this->type->convertToPHPValue($dbValue, $this->platform);

        self::assertNotNull($hydrated);
        self::assertTrue($original->equals($hydrated));
    }

    public function testRoundTripForBrokerSymbol(): void
    {
        $original = ISIN::fromUnchecked('AAPL.US');
        $dbValue = $this->type->convertToDatabaseValue($original, $this->platform);
        $hydrated = $this->type->convertToPHPValue($dbValue, $this->platform);

        self::assertNotNull($hydrated);
        self::assertSame('AAPL.US', $hydrated->toString());
    }
}
