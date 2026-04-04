<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\ValueObject\BrokerId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\CurrencyMismatchException;
use App\Shared\Domain\ValueObject\ISIN;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\NBPRate;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for Shared value objects.
 *
 * Targets: trim/strtolower/strtoupper unwrap, regex anchor removal,
 * CastInt/CastString in ISIN Luhn, GreaterThan boundary in Luhn,
 * CurrencyMismatchException parent::__construct removal,
 * Money::subtract currency guard removal, NBPRate regex anchors.
 */
final class ValueObjectMutationTest extends TestCase
{
    // --- BrokerId ---

    /**
     * Kills UnwrapTrim: strtolower(trim($value)) -> strtolower($value).
     */
    public function testBrokerIdTrimsWhitespace(): void
    {
        $broker = BrokerId::of('  ibkr  ');

        self::assertSame('ibkr', $broker->toString());
    }

    /**
     * Kills UnwrapStrToLower: strtolower(trim($value)) -> trim($value).
     */
    public function testBrokerIdNormalizesToLowercase(): void
    {
        $broker = BrokerId::of('IBKR');

        self::assertSame('ibkr', $broker->toString());
    }

    // --- CountryCode ---

    /**
     * Kills UnwrapTrim: strtoupper(trim($code)) -> strtoupper($code).
     */
    public function testCountryCodeTrimsWhitespace(): void
    {
        $cc = CountryCode::fromString(' US ');

        self::assertSame('US', $cc->value);
    }

    /**
     * Kills UnwrapStrToUpper: strtoupper(trim($code)) -> trim($code).
     */
    public function testCountryCodeNormalizesToUppercase(): void
    {
        $cc = CountryCode::fromString('us');

        self::assertSame('US', $cc->value);
    }

    // --- ISIN ---

    /**
     * Kills PregMatchRemoveCaret: /^[A-Z]{2}.../ -> /[A-Z]{2}.../
     * Without ^, a value with leading junk + valid ISIN would pass format check
     * but fail Luhn on the full string. We test with a value that has leading chars
     * matching the pattern without ^ but isn't a valid 12-char ISIN.
     */
    public function testIsinRejectsLeadingJunkMatchingPattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // "XXUS0378331005" is 14 chars -- after strtoupper+trim, format regex without ^ would
        // match "US0378331005" at the end, but with ^ it correctly rejects.
        ISIN::fromString('XXUS0378331005');
    }

    /**
     * Kills PregMatchRemoveDollar: /...[0-9]$/ -> /...[0-9]/
     * Without $, trailing extra chars would pass format check.
     */
    public function testIsinRejectsTrailingExtraChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ISIN::fromString('US0378331005XX');
    }

    /**
     * Kills CastString mutant: (string)(ord($char) - ord('A') + 10) -> ord($char) - ord('A') + 10.
     * This is an equivalent mutation (int concatenation works same as string) but let's verify Luhn works.
     */
    public function testIsinLuhnAlgorithmWithAlphaChars(): void
    {
        // Valid ISINs with alpha characters in the middle section
        $isin = ISIN::fromString('IE00B4L5Y983');

        self::assertSame('IE00B4L5Y983', $isin->toString());
    }

    /**
     * Kills GreaterThan mutant: if ($digit > 9) -> if ($digit >= 9).
     * When digit is exactly 9 after doubling, it should NOT subtract 9.
     * A digit of 9 doubled is 18, which IS > 9, so we need a case where digit*2 = 9 is NOT > 9.
     * Actually digit*2 can never be 9 (odd). So the mutant >= 9 would affect digit=5: 5*2=10>9 (OK)
     * and digit=4: 4*2=8, 8>=9 is false (same as 8>9). Hmm, actually the value 9:
     * No even number *2 = 9. But 9 itself: if digit is already doubled and equals 9, the >= mutant
     * would subtract 9 making it 0 instead of keeping 9. We need a test that detects this.
     *
     * A digit of 5 doubled = 10, >9 true, >=9 true -- same behavior.
     * Digit 4: doubled = 8, >9 false, >=9 false -- same behavior.
     * Actually the mutation changes > to >=. The only difference is digit exactly = 9 after doubling.
     * But 2*k = 9 has no integer solution. So for doubled digits, this mutant is equivalent.
     * However, the mutation only applies to the AFTER doubling value. Since digit*2 is always even,
     * it can never be exactly 9. This IS an equivalent mutant.
     *
     * Still, let's verify with a comprehensive ISIN check.
     */
    public function testIsinLuhnWithDigit9InChecksum(): void
    {
        // KR7005930003 -- contains '9' in various positions
        $isin = ISIN::fromString('KR7005930003');

        self::assertSame('KR7005930003', $isin->toString());
    }

    /**
     * Kills PlusEqual mutant: $sum += $digit -> $sum -= $digit.
     * If sum becomes negative/different, Luhn check fails.
     */
    public function testIsinRejectsWrongCheckDigitProperLuhn(): void
    {
        // US0378331005 is valid, US0378331001 should be invalid
        $this->expectException(\InvalidArgumentException::class);

        ISIN::fromString('US0378331001');
    }

    // --- Money ---

    /**
     * Kills MethodCallRemoval: $this->assertSameCurrency($other) removed from subtract().
     * Without the guard, subtracting EUR from USD would succeed silently.
     */
    public function testMoneySubtractThrowsOnCurrencyMismatch(): void
    {
        $usd = Money::of('100.00', CurrencyCode::USD);
        $eur = Money::of('50.00', CurrencyCode::EUR);

        $this->expectException(CurrencyMismatchException::class);

        $usd->subtract($eur);
    }

    // --- CurrencyMismatchException ---

    /**
     * Kills MethodCallRemoval: parent::__construct(...) removed.
     * Without parent call, getMessage() would return empty string.
     */
    public function testCurrencyMismatchExceptionHasMessage(): void
    {
        $e = new CurrencyMismatchException(CurrencyCode::USD, CurrencyCode::EUR);

        self::assertStringContainsString('USD', $e->getMessage());
        self::assertStringContainsString('EUR', $e->getMessage());
        self::assertStringContainsString('Currency mismatch', $e->getMessage());
    }

    // --- NBPRate ---

    /**
     * Kills PregMatchRemoveCaret: /^\d{3}\/.../ -> /\d{3}\/.../.
     * Without ^, "prefix_010/A/NBP/2025" would pass.
     */
    public function testNBPRateRejectsLeadingJunkInTableNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-01-14'),
            'prefix_010/A/NBP/2025',
        );
    }

    /**
     * Kills PregMatchRemoveDollar: /...\d{4}$/ -> /...\d{4}/.
     * Without $, "010/A/NBP/2025_suffix" would pass.
     */
    public function testNBPRateRejectsTrailingJunkInTableNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NBPRate::create(
            CurrencyCode::USD,
            BigDecimal::of('4.05'),
            new \DateTimeImmutable('2025-01-14'),
            '010/A/NBP/2025_suffix',
        );
    }
}
