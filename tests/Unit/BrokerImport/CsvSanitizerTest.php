<?php

declare(strict_types=1);

namespace App\Tests\Unit\BrokerImport;

use App\BrokerImport\Infrastructure\Adapter\CsvSanitizer;
use PHPUnit\Framework\TestCase;

final class CsvSanitizerTest extends TestCase
{
    use CsvSanitizer {
        sanitize as public exposedSanitize;
    }

    public function testStripsLeadingEquals(): void
    {
        self::assertSame('cmd', $this->exposedSanitize('=cmd'));
    }

    public function testStripsLeadingPlus(): void
    {
        self::assertSame('cmd', $this->exposedSanitize('+cmd'));
    }

    public function testStripsLeadingAt(): void
    {
        self::assertSame('SUM(A1)', $this->exposedSanitize('@SUM(A1)'));
    }

    public function testStripsLeadingTab(): void
    {
        self::assertSame('value', $this->exposedSanitize("\tvalue"));
    }

    public function testStripsLeadingCarriageReturn(): void
    {
        self::assertSame('value', $this->exposedSanitize("\rvalue"));
    }

    /**
     * P2-037: Dash before digit is a negative number, NOT a formula injection.
     * Sanitizer must preserve it.
     */
    public function testPreservesNegativeNumbers(): void
    {
        self::assertSame('-1234.56', $this->exposedSanitize('-1234.56'));
    }

    public function testPreservesNegativeIntegerNumbers(): void
    {
        self::assertSame('-100', $this->exposedSanitize('-100'));
    }

    public function testPreservesNegativeWithLeadingZero(): void
    {
        self::assertSame('-0.50', $this->exposedSanitize('-0.50'));
    }

    /**
     * Dash followed by a letter IS a potential formula injection vector.
     */
    public function testStripsDashBeforeLetter(): void
    {
        self::assertSame('cmd', $this->exposedSanitize('-cmd'));
    }

    /**
     * Dash followed by another formula character should be stripped.
     */
    public function testStripsDashBeforeEquals(): void
    {
        self::assertSame('1+2', $this->exposedSanitize('-=1+2'));
    }

    public function testPreservesCleanValues(): void
    {
        self::assertSame('Apple Inc.', $this->exposedSanitize('Apple Inc.'));
        self::assertSame('1234.56', $this->exposedSanitize('1234.56'));
        self::assertSame('', $this->exposedSanitize(''));
    }
}
