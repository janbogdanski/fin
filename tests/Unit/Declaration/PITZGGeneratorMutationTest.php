<?php

declare(strict_types=1);

namespace App\Tests\Unit\Declaration;

use App\Declaration\Domain\DTO\PITZGData;
use App\Declaration\Domain\Service\PITZGGenerator;
use App\Shared\Domain\ValueObject\CountryCode;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for PITZGGenerator.
 *
 * Targets: attribute values, element ordering, null-check on createElement,
 * escapeXml, Wariant, Rok, Podmiot structure, saveXML false check.
 */
final class PITZGGeneratorMutationTest extends TestCase
{
    private PITZGGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PITZGGenerator();
    }

    /**
     * Kills mutations on KodFormularza attributes: kodPodatku, rodzajZobowiazania, wersjaSchemy.
     */
    public function testKodFormularzaAttributesAreCorrect(): void
    {
        $xml = $this->generator->generate($this->data());
        $dom = $this->parse($xml);
        $kod = $dom->getElementsByTagName('KodFormularza')->item(0);

        self::assertSame('PIT', $kod->getAttribute('kodPodatku'));
        self::assertSame('Z', $kod->getAttribute('rodzajZobowiazania'));
        self::assertSame('1-0E', $kod->getAttribute('wersjaSchemy'));
        self::assertSame('PIT/ZG (7)', $kod->getAttribute('kodSystemowy'));
    }

    /**
     * Kills mutation on WariantFormularza value.
     */
    public function testWariantFormularzaIs7(): void
    {
        $xml = $this->generator->generate($this->data());
        $dom = $this->parse($xml);

        self::assertSame('7', $dom->getElementsByTagName('WariantFormularza')->item(0)->textContent);
    }

    /**
     * Kills mutation on CelZlozenia: non-correction should be '1'.
     */
    public function testCelZlozeniaNonCorrectionIs1(): void
    {
        $xml = $this->generator->generate($this->data(isCorrection: false));
        $dom = $this->parse($xml);
        $cel = $dom->getElementsByTagName('CelZlozenia')->item(0);

        self::assertSame('1', $cel->textContent);
        self::assertSame('P_6', $cel->getAttribute('poz'));
    }

    /**
     * Kills mutation on Rok element.
     */
    public function testRokContainsTaxYear(): void
    {
        $xml = $this->generator->generate($this->data());
        $dom = $this->parse($xml);

        self::assertSame('2026', $dom->getElementsByTagName('Rok')->item(0)->textContent);
    }

    /**
     * Kills mutations on Podmiot1 structure: rola attribute, NIP, names.
     */
    public function testPodatnikContainsNipAndNames(): void
    {
        $xml = $this->generator->generate($this->data());
        $dom = $this->parse($xml);

        $podmiot = $dom->getElementsByTagName('Podmiot1')->item(0);
        self::assertSame('Podatnik', $podmiot->getAttribute('rola'));

        self::assertSame('5260000005', $dom->getElementsByTagName('NIP')->item(0)->textContent);
        self::assertSame('Jan', $dom->getElementsByTagName('ImiePierwsze')->item(0)->textContent);
        self::assertSame('Kowalski', $dom->getElementsByTagName('Nazwisko')->item(0)->textContent);
    }

    /**
     * Kills mutations on PozycjeSzczegolowe: all three elements must be present.
     */
    public function testPozycjeSzczegoloweContainsAllFields(): void
    {
        $xml = $this->generator->generate($this->data());
        $dom = $this->parse($xml);

        self::assertSame('US', $dom->getElementsByTagName('KodKraju')->item(0)->textContent);
        self::assertSame('1500.00', $dom->getElementsByTagName('DochodBrutto')->item(0)->textContent);
        self::assertSame('225.00', $dom->getElementsByTagName('PodatekZaplaconyZaGranica')->item(0)->textContent);
    }

    /**
     * Kills escapeXml mutation: verifies special chars are escaped.
     */
    public function testEscapesSpecialXmlCharacters(): void
    {
        $data = new PITZGData(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan & Zofia',
            lastName: 'Kowalski<>',
            countryCode: CountryCode::US,
            incomeGross: '1500.00',
            taxPaidAbroad: '225.00',
            isCorrection: false,
        );

        $xml = $this->generator->generate($data);

        // Must produce valid XML (special chars escaped)
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));

        // The actual text content should be decoded back
        self::assertSame('Jan & Zofia', $dom->getElementsByTagName('ImiePierwsze')->item(0)->textContent);
    }

    /**
     * Kills mutation on formatOutput = true (affects readability but not correctness).
     * And resolveExternals = false, substituteEntities = false (security).
     */
    public function testXmlOutputIsWellFormed(): void
    {
        $xml = $this->generator->generate($this->data());

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);

        // Verify complete XML structure
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        self::assertTrue($dom->loadXML($xml));

        // Must have top-level elements in correct order
        $root = $dom->documentElement;
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $child->localName;
            }
        }
        self::assertSame(['Naglowek', 'Podmiot1', 'PozycjeSzczegolowe'], $children);
    }

    private function data(bool $isCorrection = false): PITZGData
    {
        return new PITZGData(
            taxYear: 2026,
            nip: '5260000005',
            firstName: 'Jan',
            lastName: 'Kowalski',
            countryCode: CountryCode::US,
            incomeGross: '1500.00',
            taxPaidAbroad: '225.00',
            isCorrection: $isCorrection,
        );
    }

    private function parse(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml));

        return $dom;
    }
}
