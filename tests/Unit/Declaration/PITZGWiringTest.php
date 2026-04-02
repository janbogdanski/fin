<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Shared\Domain\ValueObject\CountryCode;
use App\TaxCalc\Application\Query\TaxSummaryDividendCountry;
use PHPUnit\Framework\TestCase;

/**
 * US-S8-03: Verifies the wiring between TaxSummaryDividendCountry data
 * and PITZGData/PITZGGenerator — the same mapping used in DeclarationController::pitzg().
 */
final class PITZGWiringTest extends TestCase
{
    public function testBuildsPITZGDataFromDividendCountryAndGeneratesValidXml(): void
    {
        $dividendCountry = new TaxSummaryDividendCountry(
            countryCode: 'US',
            grossDividendPLN: '5000.00',
            whtPaidPLN: '750.00',
            polishTaxDue: '200.00',
        );

        $pitzgData = new PITZGData(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Anna',
            lastName: 'Kowalska',
            countryCode: CountryCode::fromString($dividendCountry->countryCode),
            incomeGross: $dividendCountry->grossDividendPLN,
            taxPaidAbroad: $dividendCountry->whtPaidPLN,
            isCorrection: false,
        );

        $generator = new PITZGGenerator();
        $xml = $generator->generate($pitzgData);

        // AC2: Valid XML
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'Generated PIT/ZG must be valid XML');

        // AC4: Verify content matches input data
        self::assertSame('US', $dom->getElementsByTagName('KodKraju')->item(0)->textContent);
        self::assertSame('5000.00', $dom->getElementsByTagName('DochodBrutto')->item(0)->textContent);
        self::assertSame('750.00', $dom->getElementsByTagName('PodatekZaplaconyZaGranica')->item(0)->textContent);
        self::assertSame('5260000005', $dom->getElementsByTagName('NIP')->item(0)->textContent);
        self::assertSame('Anna', $dom->getElementsByTagName('ImiePierwsze')->item(0)->textContent);
        self::assertSame('Kowalska', $dom->getElementsByTagName('Nazwisko')->item(0)->textContent);
    }

    public function testXmlOutputContainsContentTypeHeaders(): void
    {
        $pitzgData = new PITZGData(
            taxYear: 2025,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Nowak',
            countryCode: CountryCode::DE,
            incomeGross: '3000.00',
            taxPaidAbroad: '789.90',
            isCorrection: false,
        );

        $generator = new PITZGGenerator();
        $xml = $generator->generate($pitzgData);

        // Verify it starts with XML declaration
        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);

        // Verify the country code is DE
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        self::assertSame('DE', $dom->getElementsByTagName('KodKraju')->item(0)->textContent);
    }

    public function testMultipleCountriesProduceSeparateXmlDocuments(): void
    {
        $generator = new PITZGGenerator();
        $countries = [
            [
                'code' => 'US',
                'gross' => '5000.00',
                'wht' => '750.00',
            ],
            [
                'code' => 'DE',
                'gross' => '2000.00',
                'wht' => '527.50',
            ],
            [
                'code' => 'IE',
                'gross' => '1500.00',
                'wht' => '375.00',
            ],
        ];

        foreach ($countries as $c) {
            $data = new PITZGData(
                taxYear: 2025,
                nip: '5260000005',
                firstName: 'Jan',
                lastName: 'Kowalski',
                countryCode: CountryCode::fromString($c['code']),
                incomeGross: $c['gross'],
                taxPaidAbroad: $c['wht'],
                isCorrection: false,
            );

            $xml = $generator->generate($data);
            $dom = new \DOMDocument();
            self::assertTrue($dom->loadXML($xml));
            self::assertSame($c['code'], $dom->getElementsByTagName('KodKraju')->item(0)->textContent);
            self::assertSame($c['gross'], $dom->getElementsByTagName('DochodBrutto')->item(0)->textContent);
        }
    }
}
