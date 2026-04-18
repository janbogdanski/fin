<?php

declare(strict_types=1);

namespace App\Declaration\Domain\Service;

use App\Declaration\Domain\DTO\PITZGData;

/**
 * Generuje XML PIT/ZG — zalacznik o dochodach zagranicznych.
 *
 * Jeden zalacznik per kraj. Dolaczany do PIT-38 jako osobny plik XML
 * przy wysylce e-Deklaracji.
 *
 * Pure PHP — zero zaleznosci od frameworka.
 */
final class PITZGGenerator
{
    private const NAMESPACE_URI = 'http://crd.gov.pl/wzor/2024/12/05/13431/';

    private const WERSJA_SCHEMATU = '1-0E';

    private const KOD_SYSTEMOWY = 'PIT/ZG (7)';

    private const WARIANT = '7';

    public function generate(PITZGData $data): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $dom->formatOutput = true;

        $deklaracja = $dom->createElementNS(self::NAMESPACE_URI, 'Deklaracja');
        $dom->appendChild($deklaracja);

        $this->appendNaglowek($dom, $deklaracja, $data);
        $this->appendPodatnik($dom, $deklaracja, $data);
        $this->appendPozycjeSzczegolowe($dom, $deklaracja, $data);

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize PIT/ZG XML');
        }

        return $xml;
    }

    private function appendNaglowek(\DOMDocument $dom, \DOMElement $parent, PITZGData $data): void
    {
        $naglowek = $this->createElement($dom, $parent, 'Naglowek');

        $kodFormularza = $this->createElement($dom, $naglowek, 'KodFormularza', 'PIT/ZG');
        $kodFormularza->setAttribute('kodSystemowy', self::KOD_SYSTEMOWY);
        $kodFormularza->setAttribute('kodPodatku', 'PIT');
        $kodFormularza->setAttribute('rodzajZobowiazania', 'Z');
        $kodFormularza->setAttribute('wersjaSchemy', self::WERSJA_SCHEMATU);

        $this->createElement($dom, $naglowek, 'WariantFormularza', self::WARIANT);

        $celZlozenia = $this->createElement($dom, $naglowek, 'CelZlozenia', $data->isCorrection ? '2' : '1');
        $celZlozenia->setAttribute('poz', 'P_6');

        $this->createElement($dom, $naglowek, 'Rok', (string) $data->taxYear);
    }

    private function appendPodatnik(\DOMDocument $dom, \DOMElement $parent, PITZGData $data): void
    {
        $podatnik = $this->createElement($dom, $parent, 'Podmiot1');
        $podatnik->setAttribute('rola', 'Podatnik');

        $osobaFizyczna = $this->createElement($dom, $podatnik, 'OsobaFizyczna');

        if ($data->nip !== null) {
            $this->createElement($dom, $osobaFizyczna, 'NIP', $data->nip);
        } else {
            $this->createElement($dom, $osobaFizyczna, 'PESEL', $data->pesel);
        }

        $this->createElement($dom, $osobaFizyczna, 'ImiePierwsze', $data->firstName);
        $this->createElement($dom, $osobaFizyczna, 'Nazwisko', $data->lastName);
    }

    private function appendPozycjeSzczegolowe(\DOMDocument $dom, \DOMElement $parent, PITZGData $data): void
    {
        $pozycje = $this->createElement($dom, $parent, 'PozycjeSzczegolowe');

        $this->createElement($dom, $pozycje, 'KodKraju', $data->countryCode->value);
        $this->createElement($dom, $pozycje, 'DochodBrutto', $data->incomeGross);
        $this->createElement($dom, $pozycje, 'PodatekZaplaconyZaGranica', $data->taxPaidAbroad);
    }

    private function createElement(\DOMDocument $dom, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $element = $value !== null
            ? $dom->createElement($name, $this->escapeXml($value))
            : $dom->createElement($name);

        $parent->appendChild($element);

        return $element;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
