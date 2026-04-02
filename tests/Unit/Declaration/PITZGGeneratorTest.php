<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Shared\Domain\ValueObject\CountryCode;
use PHPUnit\Framework\TestCase;

final class PITZGGeneratorTest extends TestCase
{
    private PITZGGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PITZGGenerator();
    }

    public function testGeneratesValidXML(): void
    {
        $xml = $this->generator->generate($this->usData());

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));
        self::assertSame('Deklaracja', $dom->documentElement->localName);
    }

    public function testContainsCountryCode(): void
    {
        $xml = $this->generator->generate($this->usData());

        $dom = $this->parseXml($xml);
        $nodes = $dom->getElementsByTagName('KodKraju');

        self::assertSame(1, $nodes->length);
        self::assertSame('US', $nodes->item(0)->textContent);
    }

    public function testContainsIncomeAndTax(): void
    {
        $xml = $this->generator->generate($this->usData());

        $dom = $this->parseXml($xml);

        self::assertSame('1500.00', $dom->getElementsByTagName('DochodBrutto')->item(0)->textContent);
        self::assertSame('225.00', $dom->getElementsByTagName('PodatekZaplaconyZaGranica')->item(0)->textContent);
    }

    public function testNamespaceIsDifferentFromPIT38(): void
    {
        $xml = $this->generator->generate($this->usData());

        $dom = $this->parseXml($xml);

        self::assertSame(
            'http://crd.gov.pl/wzor/2024/12/05/13431/',
            $dom->documentElement->namespaceURI,
        );
    }

    public function testKodFormularzaIsPITZG(): void
    {
        $xml = $this->generator->generate($this->usData());

        $dom = $this->parseXml($xml);
        $kod = $dom->getElementsByTagName('KodFormularza')->item(0);

        self::assertSame('PIT/ZG', $kod->textContent);
        self::assertSame('PIT/ZG (7)', $kod->getAttribute('kodSystemowy'));
    }

    public function testCorrectionFlag(): void
    {
        $data = new PITZGData(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            countryCode: CountryCode::US,
            incomeGross: '1500.00',
            taxPaidAbroad: '225.00',
            isCorrection: true,
        );

        $xml = $this->generator->generate($data);
        $dom = $this->parseXml($xml);

        self::assertSame('2', $dom->getElementsByTagName('CelZlozenia')->item(0)->textContent);
    }

    private function usData(): PITZGData
    {
        return new PITZGData(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            countryCode: CountryCode::US,
            incomeGross: '1500.00',
            taxPaidAbroad: '225.00',
            isCorrection: false,
        );
    }

    private function parseXml(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));

        return $dom;
    }
}
