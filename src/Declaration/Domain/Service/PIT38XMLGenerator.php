<?php

declare(strict_types=1);

namespace App\Declaration\Domain\Service;

use App\Declaration\Domain\DTO\PIT38Data;

/**
 * Generuje XML PIT-38 zgodny z formatem e-Deklaracje MF.
 *
 * Wersja formularza: 17 (rok podatkowy 2025/2026).
 * Namespace: http://crd.gov.pl/wzor/2024/12/05/13430/
 *
 * Pure PHP — zero zaleznosci od frameworka.
 * XML budowany przez DOMDocument (built-in).
 *
 * UWAGA: Numery pozycji (P_22..P_51) odpowiadaja wersji 17 formularza.
 * Po uzyskaniu oficjalnego XSD nalezy zweryfikowac mapowanie.
 */
final class PIT38XMLGenerator
{
    private const NAMESPACE_URI = 'http://crd.gov.pl/wzor/2024/12/05/13430/';

    private const WERSJA_SCHEMATU = '1-0E';

    private const KOD_SYSTEMOWY = 'PIT-38 (17)';

    private const WARIANT = '17';

    public function generate(PIT38Data $data): string
    {
        if (! $data->hasCompletePersonalData()) {
            throw new \LogicException(
                'Cannot generate PIT-38 XML without complete personal data (NIP, first name, last name).',
            );
        }

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
            throw new \RuntimeException('Failed to serialize PIT-38 XML');
        }

        return $xml;
    }

    private function appendNaglowek(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $naglowek = $this->createElement($dom, $parent, 'Naglowek');

        $kodFormularza = $this->createElement($dom, $naglowek, 'KodFormularza', 'PIT-38');
        $kodFormularza->setAttribute('kodSystemowy', self::KOD_SYSTEMOWY);
        $kodFormularza->setAttribute('kodPodatku', 'PIT');
        $kodFormularza->setAttribute('rodzajZobowiazania', 'Z');
        $kodFormularza->setAttribute('wersjaSchemy', self::WERSJA_SCHEMATU);

        $this->createElement($dom, $naglowek, 'WariantFormularza', self::WARIANT);

        $celZlozenia = $this->createElement($dom, $naglowek, 'CelZlozenia', $data->isCorrection ? '2' : '1');
        $celZlozenia->setAttribute('poz', 'P_6');

        $this->createElement($dom, $naglowek, 'Rok', (string) $data->taxYear);
    }

    private function appendPodatnik(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $podatnik = $this->createElement($dom, $parent, 'Podmiot1');
        $podatnik->setAttribute('rola', 'Podatnik');

        $osobaFizyczna = $this->createElement($dom, $podatnik, 'OsobaFizyczna');
        $this->createElement($dom, $osobaFizyczna, 'NIP', $data->nip);
        $this->createElement($dom, $osobaFizyczna, 'ImiePierwsze', $data->firstName);
        $this->createElement($dom, $osobaFizyczna, 'Nazwisko', $data->lastName);
    }

    private function appendPozycjeSzczegolowe(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $pozycje = $this->createElement($dom, $parent, 'PozycjeSzczegolowe');

        // Sekcja C: odplatne zbycie papierow wartosciowych
        $this->createElement($dom, $pozycje, 'P_22', $data->equityProceeds);
        $this->createElement($dom, $pozycje, 'P_23', $data->equityCosts);
        $this->createElement($dom, $pozycje, 'P_24', $data->equityIncome);
        $this->createElement($dom, $pozycje, 'P_25', $data->equityLoss);
        $this->createElement($dom, $pozycje, 'P_26', $data->equityTaxBase);
        $this->createElement($dom, $pozycje, 'P_27', $data->equityTax);

        // Sekcja D: dywidendy zagraniczne
        $this->createElement($dom, $pozycje, 'P_28', $data->dividendGross);
        $this->createElement($dom, $pozycje, 'P_29', $data->dividendWHT);
        $this->createElement($dom, $pozycje, 'P_30', $data->dividendTaxDue);

        // Kryptowaluty
        $this->createElement($dom, $pozycje, 'P_38', $data->cryptoProceeds);
        $this->createElement($dom, $pozycje, 'P_39', $data->cryptoCosts);
        $this->createElement($dom, $pozycje, 'P_40', $data->cryptoIncome);
        $this->createElement($dom, $pozycje, 'P_41', $data->cryptoLoss);
        $this->createElement($dom, $pozycje, 'P_42', $data->cryptoTax);

        // Suma
        $this->createElement($dom, $pozycje, 'P_51', $data->totalTax);
    }

    private function createElement(\DOMDocument $dom, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $element = $value !== null
            ? $dom->createElement($name, $this->escapeXml($value))
            : $dom->createElement($name);

        $parent->appendChild($element);

        return $element;
    }

    /**
     * DOMDocument::createElement nie escapuje automatycznie &, <, >.
     * Dla bezpieczenstwa escapujemy recznie.
     */
    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
