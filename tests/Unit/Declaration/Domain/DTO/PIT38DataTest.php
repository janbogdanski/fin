<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration\Domain\DTO;

use App\Declaration\Domain\DTO\PIT38Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PIT38DataTest extends TestCase
{
    public function testAcceptsValidData(): void
    {
        $data = $this->validData();

        self::assertSame(2025, $data->taxYear);
        self::assertSame('5260000005', $data->nip);
    }

    #[DataProvider('validNipProvider')]
    public function testAcceptsValidNip(string $nip): void
    {
        $data = $this->validData(nip: $nip);

        self::assertSame($nip, $data->nip);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNipProvider(): iterable
    {
        yield 'standard test NIP' => ['5260000005'];
        yield 'another valid NIP' => ['7680000007'];
        yield 'third valid NIP' => ['1120000003'];
    }

    #[DataProvider('invalidNipProvider')]
    public function testRejectsInvalidNip(string $nip, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validData(nip: $nip);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidNipProvider(): iterable
    {
        yield 'empty' => ['', 'NIP must be exactly 10 digits'];
        yield 'too short' => ['123456789', 'NIP must be exactly 10 digits'];
        yield 'too long' => ['12345678901', 'NIP must be exactly 10 digits'];
        yield 'non-digit characters' => ['12345678AB', 'NIP must be exactly 10 digits'];
        yield 'with dashes' => ['123-456-78-90', 'NIP must be exactly 10 digits'];
        yield 'invalid check digit' => ['1234567890', 'Invalid NIP check digit'];
    }

    public function testRejectsTaxYearBelow2000(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax year must be between 2000 and 2100');

        $this->validData(taxYear: 1999);
    }

    public function testRejectsTaxYearAbove2100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax year must be between 2000 and 2100');

        $this->validData(taxYear: 2101);
    }

    public function testAcceptsBoundaryTaxYears(): void
    {
        $data2000 = $this->validData(taxYear: 2000);
        $data2100 = $this->validData(taxYear: 2100);

        self::assertSame(2000, $data2000->taxYear);
        self::assertSame(2100, $data2100->taxYear);
    }

    public function testRejectsEmptyFirstName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $this->validData(firstName: '');
    }

    public function testRejectsWhitespaceOnlyFirstName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must not be empty');

        $this->validData(firstName: '   ');
    }

    public function testRejectsEmptyLastName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $this->validData(lastName: '');
    }

    public function testRejectsWhitespaceOnlyLastName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must not be empty');

        $this->validData(lastName: '   ');
    }

    public function testAcceptsNullPersonalData(): void
    {
        $data = $this->validData(nip: null, firstName: null, lastName: null);

        self::assertNull($data->nip);
        self::assertNull($data->firstName);
        self::assertNull($data->lastName);
        self::assertFalse($data->hasCompletePersonalData());
    }

    public function testHasCompletePersonalDataReturnsTrueWhenAllSet(): void
    {
        $data = $this->validData();

        self::assertTrue($data->hasCompletePersonalData());
    }

    public function testHasCompletePersonalDataReturnsFalseWhenPartiallySet(): void
    {
        $data = $this->validData(nip: '5260000005', firstName: 'Jan', lastName: null);

        self::assertFalse($data->hasCompletePersonalData());
    }

    public function testAcceptsValidPesel(): void
    {
        $data = $this->validData(nip: null, pesel: '90090515836');

        self::assertNull($data->nip);
        self::assertSame('90090515836', $data->pesel);
        self::assertTrue($data->hasCompletePersonalData());
    }

    public function testRejectsInvalidPeselWrongLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PESEL must be exactly 11 digits');

        $this->validData(nip: null, pesel: '1234567890');
    }

    public function testRejectsInvalidPeselChecksum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PESEL check digit');

        $this->validData(nip: null, pesel: '90090515835');
    }

    public function testHasCompletePersonalDataTrueWithPeselAndNoNip(): void
    {
        $data = $this->validData(nip: null, pesel: '90090515836');

        self::assertTrue($data->hasCompletePersonalData());
    }

    private function validData(
        int $taxYear = 2025,
        ?string $nip = '5260000005',
        ?string $firstName = 'Jan',
        ?string $lastName = 'Kowalski',
        ?string $pesel = null,
    ): PIT38Data {
        return new PIT38Data(
            taxYear: $taxYear,
            nip: $nip,
            firstName: $firstName,
            lastName: $lastName,
            pesel: $pesel,
            equityProceeds: '79000.00',
            equityCosts: '68854.05',
            equityIncome: '10145.95',
            equityLoss: '0',
            equityTaxBase: '10146',
            equityTax: '1928',
            dividendGross: '1500.00',
            dividendWHT: '225.00',
            dividendTaxDue: '60',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '1988',
            isCorrection: false,
        );
    }
}
