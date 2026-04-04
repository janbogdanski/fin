<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\PolishTimezone;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for PolishTimezone.
 *
 * Targets: MethodCallRemoval of get() singleton.
 */
final class PolishTimezoneTest extends TestCase
{
    public function testReturnsEuropWarsaw(): void
    {
        $tz = PolishTimezone::get();

        self::assertSame('Europe/Warsaw', $tz->getName());
    }

    public function testReturnsSameInstance(): void
    {
        $tz1 = PolishTimezone::get();
        $tz2 = PolishTimezone::get();

        self::assertSame($tz1, $tz2);
    }
}
