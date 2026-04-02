<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\Service\PIT38XMLGenerator;
use PHPUnit\Framework\TestCase;

final class PIT38XMLGeneratorTest extends TestCase
{
    private PIT38XMLGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PIT38XMLGenerator();
    }

    public function testGeneratesValidXML(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml);

        self::assertTrue($loaded, 'Output must be valid XML');
        self::assertNotNull($dom->documentElement);
        self::assertSame('Deklaracja', $dom->documentElement->localName);
    }

    public function testContainsCorrectTaxYear(): void
    {
        $data = $this->goldenData(taxYear: 2026);

        $xml = $this->generator->generate($data);

        $dom = $this->parseXml($xml);
        $rokNodes = $dom->getElementsByTagName('Rok');

        self::assertSame(1, $rokNodes->length);
        self::assertSame('2026', $rokNodes->item(0)->textContent);
    }

    public function testContainsEquitySection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('79000.00', $this->getElementValue($dom, 'P_22'));
        self::assertSame('68854.05', $this->getElementValue($dom, 'P_23'));
        self::assertSame('10145.95', $this->getElementValue($dom, 'P_24'));
        self::assertSame('0', $this->getElementValue($dom, 'P_25'));
        self::assertSame('10146', $this->getElementValue($dom, 'P_26'));
        self::assertSame('1928', $this->getElementValue($dom, 'P_27'));
    }

    public function testContainsDividendSection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('1500.00', $this->getElementValue($dom, 'P_28'));
        self::assertSame('225.00', $this->getElementValue($dom, 'P_29'));
        self::assertSame('60', $this->getElementValue($dom, 'P_30'));
    }

    public function testContainsCryptoSection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('0', $this->getElementValue($dom, 'P_38'));
        self::assertSame('0', $this->getElementValue($dom, 'P_39'));
        self::assertSame('0', $this->getElementValue($dom, 'P_40'));
        self::assertSame('0', $this->getElementValue($dom, 'P_41'));
        self::assertSame('0', $this->getElementValue($dom, 'P_42'));
    }

    public function testTotalTaxIsSum(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        // totalTax = equityTax(1928) + dividendTaxDue(60) + cryptoTax(0) = 1988
        self::assertSame('1988', $this->getElementValue($dom, 'P_51'));
    }

    public function testCorrectionFlag(): void
    {
        $correction = $this->goldenData(isCorrection: true);
        $xml = $this->generator->generate($correction);
        $dom = $this->parseXml($xml);

        $celZlozenia = $dom->getElementsByTagName('CelZlozenia');
        self::assertSame('2', $celZlozenia->item(0)->textContent);

        $original = $this->goldenData(isCorrection: false);
        $xml = $this->generator->generate($original);
        $dom = $this->parseXml($xml);

        $celZlozenia = $dom->getElementsByTagName('CelZlozenia');
        self::assertSame('1', $celZlozenia->item(0)->textContent);
    }

    public function testZeroTaxCase(): void
    {
        $data = new PIT38Data(
            taxYear: 2026,
            nip: '1234567890',
            firstName: 'Jan',
            lastName: 'Kowalski',
            equityProceeds: '0',
            equityCosts: '0',
            equityIncome: '0',
            equityLoss: '0',
            equityTaxBase: '0',
            equityTax: '0',
            dividendGross: '0',
            dividendWHT: '0',
            dividendTaxDue: '0',
            cryptoProceeds: '0',
            cryptoCosts: '0',
            cryptoIncome: '0',
            cryptoLoss: '0',
            cryptoTax: '0',
            totalTax: '0',
            isCorrection: false,
        );

        $xml = $this->generator->generate($data);

        $dom = $this->parseXml($xml);

        self::assertSame('0', $this->getElementValue($dom, 'P_22'));
        self::assertSame('0', $this->getElementValue($dom, 'P_51'));
    }

    public function testContainsTaxpayerData(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('1234567890', $this->getElementValue($dom, 'NIP'));
        self::assertSame('Jan', $this->getElementValue($dom, 'ImiePierwsze'));
        self::assertSame('Kowalski', $this->getElementValue($dom, 'Nazwisko'));
    }

    public function testNamespaceIsCorrect(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame(
            'http://crd.gov.pl/wzor/2024/12/05/13430/',
            $dom->documentElement->namespaceURI,
        );
    }

    public function testKodFormularzaAttributes(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        $kodFormularza = $dom->getElementsByTagName('KodFormularza')->item(0);
        self::assertNotNull($kodFormularza);
        self::assertSame('PIT-38', $kodFormularza->textContent);
        self::assertSame('PIT-38 (17)', $kodFormularza->getAttribute('kodSystemowy'));
        self::assertSame('PIT', $kodFormularza->getAttribute('kodPodatku'));
        self::assertSame('Z', $kodFormularza->getAttribute('rodzajZobowiazania'));
        self::assertSame('1-0E', $kodFormularza->getAttribute('wersjaSchemy'));
    }

    /**
     * Golden Dataset z zadania — dane Tomasza z przyblizonymi wartosciami PIT-38.
     */
    private function goldenData(int $taxYear = 2026, bool $isCorrection = false): PIT38Data
    {
        return new PIT38Data(
            taxYear: $taxYear,
            nip: '1234567890',
            firstName: 'Jan',
            lastName: 'Kowalski',
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
            isCorrection: $isCorrection,
        );
    }

    private function parseXml(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml);
        self::assertTrue($loaded);

        return $dom;
    }

    private function getElementValue(\DOMDocument $dom, string $tagName): string
    {
        $nodes = $dom->getElementsByTagName($tagName);
        self::assertGreaterThanOrEqual(1, $nodes->length, "Element <{$tagName}> not found");

        return $nodes->item(0)->textContent;
    }
}
