<?php

declare(strict_types=1);

namespace App\Declaration\Domain\Service;

use App\Declaration\Domain\DTO\PIT38Data;

/**
 * Generuje XML PIT-38 zgodny z formatem e-Deklaracje MF.
 *
 * Wersja formularza: 18 (rok podatkowy 2025).
 * Namespace (targetNamespace XSD): http://crd.gov.pl/wzor/2025/10/09/13914/
 * Oficjalny XSD: http://crd.gov.pl/wzor/2025/10/09/13914/schemat.xsd
 *
 * Pure PHP — zero zaleznosci od frameworka.
 * XML budowany przez DOMDocument (built-in).
 *
 * Mapowanie pozycji (PIT-38(18)):
 *   Sekcja akcje/papiery (art. 30b ust. 1):
 *     P_20  przychody (main securities)
 *     P_21  koszty uzyskania (main securities)
 *     P_26  razem przychody
 *     P_27  razem koszty
 *     P_28  razem dochod (XOR P_29 strata)
 *     P_31  podstawa obliczenia podatku
 *     P_33  podatek od dochodow art. 30b ust. 1
 *     P_35  podatek nalezny art. 30b ust. 1
 *   Kryptowaluty (art. 30b ust. 1a):
 *     P_36  przychod
 *     P_37  koszty biezacego roku
 *     P_39  dochod (XOR P_40 strata)
 *     P_43  podatek art. 30b ust. 1a  [ZAWSZE WYMAGANY w XSD]
 *     P_45  podatek nalezny art. 30b ust. 1a
 *   Dywidendy zagraniczne (art. 30a ust. 1):
 *     P_47  podatek wg stawki art. 30a (brutto)
 *     P_48  podatek zaplacony za granica
 *     P_49  roznica (podatek nalezny)
 *   Podsumowanie:
 *     P_51  podatek do zaplaty (XOR P_52 nadplata)
 *
 * UWAGA: Pola adresowe i KodUrzedu sa wymagane przez oficjalny XSD MF.
 * Bez nich XML jest poprawny strukturalnie, ale nie przejdzie pełnej walidacji schematu.
 */
final class PIT38XMLGenerator
{
    private const NAMESPACE_URI = 'http://crd.gov.pl/wzor/2025/10/09/13914/';

    private const WERSJA_SCHEMATU = '1-0E';

    private const KOD_SYSTEMOWY = 'PIT-38 (18)';

    private const KOD_PODATKU = 'PPW';

    private const WARIANT = '18';

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
        $this->appendPodmiot1($dom, $deklaracja, $data);
        $this->appendPozycjeSzczegolowe($dom, $deklaracja, $data);
        $this->appendPouczenia($dom, $deklaracja);

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
        $kodFormularza->setAttribute('kodPodatku', self::KOD_PODATKU);
        $kodFormularza->setAttribute('rodzajZobowiazania', 'Z');
        $kodFormularza->setAttribute('wersjaSchemy', self::WERSJA_SCHEMATU);

        $this->createElement($dom, $naglowek, 'WariantFormularza', self::WARIANT);

        $celZlozenia = $this->createElement($dom, $naglowek, 'CelZlozenia', $data->isCorrection ? '2' : '1');
        $celZlozenia->setAttribute('poz', 'P_6');

        $this->createElement($dom, $naglowek, 'Rok', (string) $data->taxYear);

        if ($data->kodUrzedu !== null) {
            $this->createElement($dom, $naglowek, 'KodUrzedu', $data->kodUrzedu);
        }
    }

    private function appendPodmiot1(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $podmiot1 = $this->createElement($dom, $parent, 'Podmiot1');
        $podmiot1->setAttribute('rola', 'Podatnik');

        $osobaFizyczna = $this->createElement($dom, $podmiot1, 'OsobaFizyczna');
        $this->createElement($dom, $osobaFizyczna, 'NIP', $data->nip);
        $this->createElement($dom, $osobaFizyczna, 'ImiePierwsze', $data->firstName);
        $this->createElement($dom, $osobaFizyczna, 'Nazwisko', $data->lastName);

        if ($data->hasCompleteAddress()) {
            $this->appendAdresZamieszkania($dom, $podmiot1, $data);
        }
    }

    private function appendAdresZamieszkania(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $adres = $this->createElement($dom, $parent, 'AdresZamieszkania');
        $adres->setAttribute('rodzajAdresu', 'RAD');

        $adresPol = $this->createElement($dom, $adres, 'AdresPol');
        $this->createElement($dom, $adresPol, 'KodKraju', 'PL');
        $this->createElement($dom, $adresPol, 'Wojewodztwo', $data->adresZamieszkania->wojewodztwo);
        $this->createElement($dom, $adresPol, 'Powiat', $data->adresZamieszkania->powiat);
        $this->createElement($dom, $adresPol, 'Gmina', $data->adresZamieszkania->gmina);

        if ($data->adresZamieszkania->ulica !== null) {
            $this->createElement($dom, $adresPol, 'Ulica', $data->adresZamieszkania->ulica);
        }

        $this->createElement($dom, $adresPol, 'NrDomu', $data->adresZamieszkania->nrDomu);

        if ($data->adresZamieszkania->nrLokalu !== null) {
            $this->createElement($dom, $adresPol, 'NrLokalu', $data->adresZamieszkania->nrLokalu);
        }

        $this->createElement($dom, $adresPol, 'Miejscowosc', $data->adresZamieszkania->miejscowosc);
        $this->createElement($dom, $adresPol, 'KodPocztowy', $data->adresZamieszkania->kodPocztowy);
    }

    private function appendPozycjeSzczegolowe(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $pozycje = $this->createElement($dom, $parent, 'PozycjeSzczegolowe');

        // --- Sekcja akcje/papiery wartosciowe (art. 30b ust. 1) ---
        // Cala sekcja jest opcjonalna; emitujemy gdy sa przychody lub koszty.
        $hasEquity = $this->isNonZero($data->equityProceeds) || $this->isNonZero($data->equityCosts);
        if ($hasEquity) {
            $this->createElement($dom, $pozycje, 'P_20', $data->equityProceeds);  // przychody main
            $this->createElement($dom, $pozycje, 'P_21', $data->equityCosts);    // koszty main

            // P_26/P_27: razem przychody i koszty (gdy tylko P_20/P_21, rowne im)
            $this->createElement($dom, $pozycje, 'P_26', $data->equityProceeds);
            $this->createElement($dom, $pozycje, 'P_27', $data->equityCosts);

            // P_28 (dochod) XOR P_29 (strata)
            if ($this->isNonZero($data->equityIncome)) {
                $this->createElement($dom, $pozycje, 'P_28', $data->equityIncome);
            } else {
                $this->createElement($dom, $pozycje, 'P_29', $data->equityLoss);
            }

            // Obliczenie podatku — emitujemy gdy jest podatek
            if ($this->isNonZero($data->equityTax)) {
                $this->createElement($dom, $pozycje, 'P_31', $data->equityTaxBase);  // podstawa
                $this->createElement($dom, $pozycje, 'P_33', $data->equityTax);       // podatek
                $this->createElement($dom, $pozycje, 'P_35', $data->equityTax);       // podatek nalezny (= P_33 gdy brak kredytu zagranicznego)
            }
        }

        // --- Sekcja kryptowaluty (art. 30b ust. 1a) ---
        $hasCrypto = $this->isNonZero($data->cryptoProceeds) || $this->isNonZero($data->cryptoCosts);
        if ($hasCrypto) {
            $this->createElement($dom, $pozycje, 'P_36', $data->cryptoProceeds);  // przychod
            $this->createElement($dom, $pozycje, 'P_37', $data->cryptoCosts);     // koszty biezacego roku

            // P_39 (dochod) XOR P_40 (koszty do przeniesienia na kolejny rok)
            if ($this->isNonZero($data->cryptoIncome)) {
                $this->createElement($dom, $pozycje, 'P_39', $data->cryptoIncome);
            } else {
                $this->createElement($dom, $pozycje, 'P_40', $data->cryptoLoss);
            }
        }

        // P_43 jest ZAWSZE WYMAGANY przez oficjalny XSD — podatek z art. 30b ust. 1a
        $this->createElement($dom, $pozycje, 'P_43', $data->cryptoTax);

        if ($this->isNonZero($data->cryptoTax)) {
            $this->createElement($dom, $pozycje, 'P_45', $data->cryptoTax);  // podatek nalezny (= P_43 gdy brak kredytu zagranicznego)
        }

        // --- Dywidendy zagraniczne (art. 30a ust. 1) ---
        // P_47 = brutto podatek polski = dividendTaxDue + dividendWHT
        // (poprawne gdy WHT <= podatek polski; gdy WHT > podatek, wymagana reczna korekta)
        if ($this->isNonZero($data->dividendTaxDue)) {
            $dividendGrossTax = $this->addAmounts($data->dividendTaxDue, $data->dividendWHT);
            $this->createElement($dom, $pozycje, 'P_47', $dividendGrossTax);

            if ($this->isNonZero($data->dividendWHT)) {
                $this->createElement($dom, $pozycje, 'P_48', $data->dividendWHT);
            }

            $this->createElement($dom, $pozycje, 'P_49', $data->dividendTaxDue);
        }

        // P_51 (podatek do zaplaty) lub P_52 (nadplata) — wymagany wybor
        // Emitujemy zawsze P_51; nadplata nie jest przez to narzedzie obliczana.
        $this->createElement($dom, $pozycje, 'P_51', $data->totalTax);
    }

    private function appendPouczenia(\DOMDocument $dom, \DOMElement $parent): void
    {
        // Potwierdzenie swiadomosci odpowiedzialnosci karno-skarbowej (wymagane przez XSD)
        $this->createElement($dom, $parent, 'Pouczenia', '1');
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

    private function isNonZero(string $value): bool
    {
        return (float) $value !== 0.0;
    }

    /**
     * Dodaje dwie kwoty jako stringi z zachowaniem skali.
     * Uzywane do obliczenia P_47 (brutto podatek od dywidend).
     */
    private function addAmounts(string $a, string $b): string
    {
        $dotA = strrpos($a, '.');
        $dotB = strrpos($b, '.');
        $scaleA = $dotA !== false ? strlen($a) - $dotA - 1 : 0;
        $scaleB = $dotB !== false ? strlen($b) - $dotB - 1 : 0;
        $scale = max($scaleA, $scaleB);

        $sum = (float) $a + (float) $b;

        return number_format($sum, $scale, '.', '');
    }
}
