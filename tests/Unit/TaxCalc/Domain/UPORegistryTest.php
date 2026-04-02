<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaxCalc\Domain;

use App\Shared\Domain\ValueObject\CountryCode;
use App\TaxCalc\Domain\Service\UPORegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UPORegistryTest extends TestCase
{
    private UPORegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new UPORegistry();
    }

    #[DataProvider('knownRatesProvider')]
    public function testReturnsCorrectRateForKnownCountry(CountryCode $country, string $expectedRate): void
    {
        $rate = $this->registry->getRate($country);

        self::assertTrue(
            $rate->isEqualTo($expectedRate),
            "Expected {$expectedRate} for {$country->value}, got {$rate}",
        );
    }

    /**
     * @return iterable<string, array{CountryCode, string}>
     */
    public static function knownRatesProvider(): iterable
    {
        yield 'USA (z W-8BEN)' => [CountryCode::US, '0.15'];
        yield 'UK (poprawione po review — 15%, NIE 10%)' => [CountryCode::GB, '0.15'];
        yield 'Niemcy' => [CountryCode::DE, '0.15'];
        yield 'Irlandia' => [CountryCode::IE, '0.15'];
        yield 'Holandia' => [CountryCode::NL, '0.15'];
        yield 'Szwajcaria' => [CountryCode::CH, '0.15'];
        yield 'Kanada' => [CountryCode::CA, '0.15'];
        yield 'Japonia (10%)' => [CountryCode::JP, '0.10'];
        yield 'Australia' => [CountryCode::AU, '0.15'];
        yield 'Luksemburg' => [CountryCode::LU, '0.15'];
        yield 'Francja' => [CountryCode::FR, '0.15'];
        yield 'Szwecja' => [CountryCode::SE, '0.15'];
        yield 'Norwegia' => [CountryCode::NO, '0.15'];
        yield 'Dania' => [CountryCode::DK, '0.15'];
        yield 'Finlandia' => [CountryCode::FI, '0.15'];
    }

    public function testReturnsDefaultRateWhenNoAgreement(): void
    {
        $rate = $this->registry->getRate(CountryCode::HK);

        self::assertTrue(
            $rate->isEqualTo('0.19'),
            "Expected 0.19 (default) for HK, got {$rate}",
        );
    }

    public function testHasAgreementReturnsTrueForKnownCountry(): void
    {
        self::assertTrue($this->registry->hasAgreement(CountryCode::US));
        self::assertTrue($this->registry->hasAgreement(CountryCode::GB));
        self::assertTrue($this->registry->hasAgreement(CountryCode::JP));
    }

    public function testHasAgreementReturnsFalseForUnknownCountry(): void
    {
        self::assertFalse($this->registry->hasAgreement(CountryCode::HK));
        self::assertFalse($this->registry->hasAgreement(CountryCode::SG));
        self::assertFalse($this->registry->hasAgreement(CountryCode::CN));
    }

    /**
     * P2-005: UPORegistry accepts rates from config (DI).
     */
    public function testAcceptsCustomRatesViaConstructor(): void
    {
        $customRates = [
            'US' => '0.10',
            'DE' => '0.20',
        ];

        $registry = new UPORegistry($customRates, '0.25');

        self::assertTrue($registry->getRate(CountryCode::US)->isEqualTo('0.10'));
        self::assertTrue($registry->getRate(CountryCode::DE)->isEqualTo('0.20'));

        // GB not in custom rates, should use custom default
        self::assertTrue($registry->getRate(CountryCode::GB)->isEqualTo('0.25'));
        self::assertFalse($registry->hasAgreement(CountryCode::GB));
    }

    /**
     * P2-005: Backward compatibility — no-arg construction uses built-in defaults.
     */
    public function testBackwardCompatibleNoArgConstruction(): void
    {
        $registry = new UPORegistry();

        self::assertTrue($registry->getRate(CountryCode::US)->isEqualTo('0.15'));
        self::assertTrue($registry->getRate(CountryCode::JP)->isEqualTo('0.10'));
        self::assertTrue($registry->getRate(CountryCode::HK)->isEqualTo('0.19'));
    }
}
