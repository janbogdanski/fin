<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PIT38Data;
use App\Declaration\Domain\DTO\PolishAddress;
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

    /**
     * PIT-38(18): pozycje dla akcji/papierow wartosciowych.
     * P_20/P_21 przychody/koszty, P_26/P_27 sumy, P_28 dochod, P_31 podstawa, P_33/P_35 podatek.
     */
    public function testContainsEquitySection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('79000.00', $this->getElementValue($dom, 'P_20'));   // przychody
        self::assertSame('68854.05', $this->getElementValue($dom, 'P_21'));   // koszty
        self::assertSame('79000.00', $this->getElementValue($dom, 'P_26'));   // razem przychody
        self::assertSame('68854.05', $this->getElementValue($dom, 'P_27'));   // razem koszty
        self::assertSame('10145.95', $this->getElementValue($dom, 'P_28'));   // dochod
        self::assertSame('10146', $this->getElementValue($dom, 'P_31'));       // podstawa
        self::assertSame('1928', $this->getElementValue($dom, 'P_33'));        // podatek
        self::assertSame('1928', $this->getElementValue($dom, 'P_35'));        // podatek nalezny
    }

    /**
     * PIT-38(18): dywidendy zagraniczne (art. 30a ust. 1).
     * P_47 brutto podatek, P_48 zaplacone za granica, P_49 nalezny.
     */
    public function testContainsDividendSection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        // P_47 = dividendTaxDue + dividendWHT = 60 + 225.00 = 285.00
        self::assertSame('285.00', $this->getElementValue($dom, 'P_47'));
        self::assertSame('225.00', $this->getElementValue($dom, 'P_48'));
        self::assertSame('60', $this->getElementValue($dom, 'P_49'));
    }

    /**
     * PIT-38(18): kryptowaluty (art. 30b ust. 1a).
     * Cala sekcja kryptowalut (P_36/P_37/P_39|P_40 i P_41/P_42/P_43) jest opcjonalna —
     * emitowana tylko gdy hasCrypto (przychody lub koszty z kryptowalut > 0).
     * Golden data ma zerowe kryptowaluty — sekcja nie powinna byc emitowana.
     */
    public function testContainsCryptoSection(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        // Brak kryptowalut — cala sekcja nie powinna byc emitowana
        self::assertSame(0, $dom->getElementsByTagName('P_36')->length, 'P_36 should not be emitted when crypto is zero');
        self::assertSame(0, $dom->getElementsByTagName('P_37')->length, 'P_37 should not be emitted when crypto is zero');
        self::assertSame(0, $dom->getElementsByTagName('P_43')->length, 'P_43 should not be emitted when crypto is zero');
        self::assertSame(0, $dom->getElementsByTagName('P_41')->length, 'P_41 should not be emitted when crypto is zero');
        self::assertSame(0, $dom->getElementsByTagName('P_42')->length, 'P_42 should not be emitted when crypto is zero');
    }

    public function testTotalTaxIsP51(): void
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

    /**
     * P2-039: Equity loss scenario — P_29 (strata) zamiast P_28 (dochod).
     */
    public function testEquityLossScenario(): void
    {
        $data = new PIT38Data(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            equityProceeds: '5000.00',
            equityCosts: '8000.00',
            equityIncome: '0',
            equityLoss: '3000.00',
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

        self::assertSame('5000.00', $this->getElementValue($dom, 'P_20'));
        self::assertSame('8000.00', $this->getElementValue($dom, 'P_21'));
        self::assertSame('5000.00', $this->getElementValue($dom, 'P_26'));
        self::assertSame('8000.00', $this->getElementValue($dom, 'P_27'));

        // Loss scenario: P_29 musi byc emitowane zamiast P_28
        self::assertSame('3000.00', $this->getElementValue($dom, 'P_29'));
        self::assertSame(0, $dom->getElementsByTagName('P_28')->length, 'P_28 must not be emitted in loss scenario');

        // P_33 jest ZAWSZE wymagany przez oficjalny XSD (even when tax is zero)
        self::assertSame('0', $this->getElementValue($dom, 'P_33'));
        // P_35 jest opcjonalny — nie emitowany gdy brak podatku
        self::assertSame(0, $dom->getElementsByTagName('P_35')->length, 'P_35 should not be emitted when tax is zero');

        // Brak kryptowalut — sekcja kryptowalut nie powinna byc emitowana
        self::assertSame(0, $dom->getElementsByTagName('P_43')->length, 'P_43 should not be emitted when crypto is zero');
        self::assertSame('0', $this->getElementValue($dom, 'P_51'));
    }

    public function testZeroTaxCase(): void
    {
        $data = new PIT38Data(
            taxYear: 2026,
            nip: '5260000005',
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

        // Brak przychodow — opcjonalna grupa P_20/P_21 nie powinna byc emitowana
        self::assertSame(0, $dom->getElementsByTagName('P_20')->length, 'P_20 should not be emitted when equity is zero');

        // P_26/P_27/P_28/P_31/P_32/P_33 sa ZAWSZE wymagane (nawet przy zerach)
        self::assertSame('0.00', $this->getElementValue($dom, 'P_26'));
        self::assertSame('0.00', $this->getElementValue($dom, 'P_27'));
        self::assertSame('0.00', $this->getElementValue($dom, 'P_28'));
        self::assertSame('0', $this->getElementValue($dom, 'P_31'));
        self::assertSame('19', $this->getElementValue($dom, 'P_32'));
        self::assertSame('0', $this->getElementValue($dom, 'P_33'));

        // Brak kryptowalut — sekcja kryptowalut nie powinna byc emitowana
        self::assertSame(0, $dom->getElementsByTagName('P_43')->length, 'P_43 should not be emitted when crypto is zero');
        self::assertSame('0', $this->getElementValue($dom, 'P_51'));
    }

    public function testContainsTaxpayerData(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame('5260000005', $this->getElementValue($dom, 'NIP'));
        self::assertSame('Jan', $this->getElementValue($dom, 'ImiePierwsze'));
        self::assertSame('Kowalski', $this->getElementValue($dom, 'Nazwisko'));
    }

    public function testNamespaceIsCorrect(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        self::assertSame(
            'http://crd.gov.pl/wzor/2025/10/09/13914/',
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
        self::assertSame('PIT-38 (18)', $kodFormularza->getAttribute('kodSystemowy'));
        self::assertSame('PPW', $kodFormularza->getAttribute('kodPodatku'));
        self::assertSame('Z', $kodFormularza->getAttribute('rodzajZobowiazania'));
        self::assertSame('1-0E', $kodFormularza->getAttribute('wersjaSchemy'));
    }

    public function testWariantFormularza(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        $wariant = $dom->getElementsByTagName('WariantFormularza')->item(0);
        self::assertNotNull($wariant);
        self::assertSame('18', $wariant->textContent);
    }

    public function testPouczeniaPresentWithValueOne(): void
    {
        $xml = $this->generator->generate($this->goldenData());

        $dom = $this->parseXml($xml);

        $pouczenia = $dom->getElementsByTagName('Pouczenia');
        self::assertSame(1, $pouczenia->length, 'Pouczenia element must be present');
        self::assertSame('1', $pouczenia->item(0)->textContent);
    }

    public function testKodUrzeduEmittedWhenProvided(): void
    {
        $data = $this->goldenData(kodUrzedu: '0271');
        $xml = $this->generator->generate($data);
        $dom = $this->parseXml($xml);

        $kodUrzedu = $dom->getElementsByTagName('KodUrzedu');
        self::assertSame(1, $kodUrzedu->length);
        self::assertSame('0271', $kodUrzedu->item(0)->textContent);
    }

    public function testKodUrzeduOmittedWhenNull(): void
    {
        $xml = $this->generator->generate($this->goldenData());
        $dom = $this->parseXml($xml);

        self::assertSame(0, $dom->getElementsByTagName('KodUrzedu')->length, 'KodUrzedu must not be emitted when null');
    }

    public function testAddressEmittedWhenComplete(): void
    {
        $data = $this->goldenData(
            adresZamieszkania: new PolishAddress(
                miejscowosc: 'Warszawa',
                nrDomu: '1',
                kodPocztowy: '00-001',
                wojewodztwo: 'mazowieckie',
                powiat: 'Warszawa',
                gmina: 'Warszawa',
            ),
        );
        $xml = $this->generator->generate($data);
        $dom = $this->parseXml($xml);

        self::assertSame(1, $dom->getElementsByTagName('AdresZamieszkania')->length);
        self::assertSame('Warszawa', $this->getElementValue($dom, 'Miejscowosc'));
        self::assertSame('00-001', $this->getElementValue($dom, 'KodPocztowy'));
        self::assertSame('PL', $this->getElementValue($dom, 'KodKraju'));
    }

    public function testAddressOmittedWhenIncomplete(): void
    {
        $xml = $this->generator->generate($this->goldenData());
        $dom = $this->parseXml($xml);

        self::assertSame(0, $dom->getElementsByTagName('AdresZamieszkania')->length, 'Address must not be emitted when incomplete');
    }

    /**
     * P2-038: Special XML characters in names must be escaped properly,
     * but DOMDocument must NOT double-escape them (i.e., "&amp;amp;" is a bug).
     */
    public function testSpecialXmlCharactersInNameAreEscapedOnce(): void
    {
        $data = new PIT38Data(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan & <Son>',
            lastName: 'O\'Kowalski',
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

        // XML must be valid
        $dom = $this->parseXml($xml);

        // textContent returns the decoded value (no entities)
        self::assertSame('Jan & <Son>', $this->getElementValue($dom, 'ImiePierwsze'));
        self::assertSame('O\'Kowalski', $this->getElementValue($dom, 'Nazwisko'));

        // Raw XML must contain single-level escaping, NOT double
        self::assertStringContainsString('Jan &amp; &lt;Son&gt;', $xml);
        self::assertStringNotContainsString('&amp;amp;', $xml);
        self::assertStringNotContainsString('&amp;lt;', $xml);
    }

    public function testRejectsIncompletePersonalData(): void
    {
        $data = new PIT38Data(
            taxYear: 2026,
            nip: null,
            firstName: null,
            lastName: null,
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

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot generate PIT-38 XML without complete personal data');

        $this->generator->generate($data);
    }

    /**
     * Golden Dataset z zadania — dane Tomasza z przyblizonymi wartosciami PIT-38.
     */
    private function goldenData(
        int $taxYear = 2026,
        bool $isCorrection = false,
        ?string $kodUrzedu = null,
        ?PolishAddress $adresZamieszkania = null,
    ): PIT38Data {
        return new PIT38Data(
            taxYear: $taxYear,
            nip: '5260000005',
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
            kodUrzedu: $kodUrzedu,
            adresZamieszkania: $adresZamieszkania,
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
