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
 *     P_20  przychody (main securities) — opcjonalna grupa P_20/P_21
 *     P_21  koszty uzyskania (main securities)
 *     P_26  razem przychody (ZAWSZE WYMAGANA)
 *     P_27  razem koszty (ZAWSZE WYMAGANA)
 *     P_28  razem dochod XOR P_29 strata (ZAWSZE WYMAGANA — jeden z nich)
 *     P_31  podstawa obliczenia podatku — integer (ZAWSZE WYMAGANA)
 *     P_32  stawka podatku "19" (ZAWSZE WYMAGANA)
 *     P_33  podatek od dochodow art. 30b ust. 1 (ZAWSZE WYMAGANA)
 *     P_35  podatek nalezny art. 30b ust. 1 — integer (opcjonalny)
 *   Kryptowaluty (art. 30b ust. 1a):
 *     P_36  przychod (opcjonalna podgrupa)
 *     P_37  koszty biezacego roku
 *     P_39  dochod (XOR P_40 strata)
 *     P_41  podstawa obliczenia podatku — integer (opcjonalna GRUPA P_41/P_42/P_43)
 *     P_42  stawka podatku "19"
 *     P_43  podatek art. 30b ust. 1a
 *     P_45  podatek nalezny art. 30b ust. 1a — integer (opcjonalny)
 *   Dywidendy zagraniczne (art. 30a ust. 1):
 *     P_47  podatek wg stawki art. 30a (brutto)
 *     P_48  podatek zaplacony za granica
 *     P_49  roznica (podatek nalezny)
 *   Podsumowanie:
 *     P_51  podatek do zaplaty (XOR P_52 nadplata) — ZAWSZE WYMAGANA
 *
 * Typy kwot:
 *   TKwota2Nieujemna = decimal, max 2 miejsca dziesietne, nieujemna (np. "0.00")
 *   TKwotaCNieujemna = integer, nieujemny (np. "0")
 *
 * UWAGA: Pola adresowe, KodUrzedu i DataUrodzenia sa wymagane przez oficjalny XSD MF.
 */
final class PIT38XMLGenerator
{
    private const NAMESPACE_URI = 'http://crd.gov.pl/wzor/2025/10/09/13914/';

    /**
     * Namespace dla elementow z StrukturyDanych_v12-0E (typy TIdentyfikatorOsobyFizycznej1, etc.).
     * Elementy NIP, PESEL, ImiePierwsze, Nazwisko, DataUrodzenia sa definiowane w tej przestrzeni nazw
     * (elementFormDefault="qualified" wymaga kwalifikacji w instancji dokumentu XML).
     */
    private const ETD_NAMESPACE_URI = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/09/13/eD/DefinicjeTypy/';

    private const WERSJA_SCHEMATU = '1-0E';

    private const KOD_SYSTEMOWY = 'PIT-38 (18)';

    private const KOD_PODATKU = 'PPW';

    private const WARIANT = '18';

    public function generate(PIT38Data $data): string
    {
        if (! $data->hasCompletePersonalData()) {
            throw new \LogicException(
                'Cannot generate PIT-38 XML without complete personal data (NIP or PESEL, first name, last name).',
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

        // Elementy OsobaFizyczna sa zdefiniowane w przestrzeni nazw ETD (TIdentyfikatorOsobyFizycznej1).
        // Wymagaja kwalifikacji namespace (elementFormDefault="qualified" w etd schema).
        // XSD wymaga NIP albo PESEL — nigdy obu naraz.
        if ($data->nip !== null) {
            $this->createEtdElement($dom, $osobaFizyczna, 'NIP', $data->nip);
        } else {
            $this->createEtdElement($dom, $osobaFizyczna, 'PESEL', $data->pesel);
        }
        $this->createEtdElement($dom, $osobaFizyczna, 'ImiePierwsze', $data->firstName);
        $this->createEtdElement($dom, $osobaFizyczna, 'Nazwisko', $data->lastName);

        if ($data->dateOfBirth !== null) {
            $this->createEtdElement($dom, $osobaFizyczna, 'DataUrodzenia', $data->dateOfBirth);
        }

        if ($data->hasCompleteAddress()) {
            $this->appendAdresZamieszkania($dom, $podmiot1, $data);
        }
    }

    private function appendAdresZamieszkania(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $adresZamieszkania = $data->adresZamieszkania;

        if ($adresZamieszkania === null) {
            return;
        }

        $adres = $this->createElement($dom, $parent, 'AdresZamieszkania');
        $adres->setAttribute('rodzajAdresu', 'RAD');

        $adresPol = $this->createElement($dom, $adres, 'AdresPol');
        $this->createElement($dom, $adresPol, 'KodKraju', 'PL');
        $this->createElement($dom, $adresPol, 'Wojewodztwo', $adresZamieszkania->wojewodztwo);
        $this->createElement($dom, $adresPol, 'Powiat', $adresZamieszkania->powiat);
        $this->createElement($dom, $adresPol, 'Gmina', $adresZamieszkania->gmina);

        if ($adresZamieszkania->ulica !== null) {
            $this->createElement($dom, $adresPol, 'Ulica', $adresZamieszkania->ulica);
        }

        $this->createElement($dom, $adresPol, 'NrDomu', $adresZamieszkania->nrDomu);

        if ($adresZamieszkania->nrLokalu !== null) {
            $this->createElement($dom, $adresPol, 'NrLokalu', $adresZamieszkania->nrLokalu);
        }

        $this->createElement($dom, $adresPol, 'Miejscowosc', $adresZamieszkania->miejscowosc);
        $this->createElement($dom, $adresPol, 'KodPocztowy', $adresZamieszkania->kodPocztowy);
    }

    private function appendPozycjeSzczegolowe(\DOMDocument $dom, \DOMElement $parent, PIT38Data $data): void
    {
        $pozycje = $this->createElement($dom, $parent, 'PozycjeSzczegolowe');

        // --- Opcjonalna grupa P_20/P_21 (przychody/koszty ze zbycia papierow) ---
        // Emitujemy tylko gdy sa przychody lub koszty z papierow wartosciowych.
        $hasEquity = $this->isNonZero($data->equityProceeds) || $this->isNonZero($data->equityCosts);
        if ($hasEquity) {
            $this->createElement($dom, $pozycje, 'P_20', $data->equityProceeds);
            $this->createElement($dom, $pozycje, 'P_21', $data->equityCosts);
        }

        // --- Zawsze wymagane: P_26/P_27 (razem przychody i koszty) ---
        // Gdy brak papierow, razem = 0.00 (typ TKwota2Nieujemna wymaga formatu decimal).
        $p26 = $hasEquity ? $data->equityProceeds : '0.00';
        $p27 = $hasEquity ? $data->equityCosts : '0.00';
        $this->createElement($dom, $pozycje, 'P_26', $p26);
        $this->createElement($dom, $pozycje, 'P_27', $p27);

        // --- Zawsze wymagany wybor: P_28 (dochod) XOR P_29 (strata) ---
        // Gdy przychody=0 i koszty=0, emitujemy P_28=0.00.
        if ($this->isNonZero($data->equityLoss) && ! $this->isNonZero($data->equityIncome)) {
            $this->createElement($dom, $pozycje, 'P_29', $data->equityLoss);
        } else {
            $equityIncomeVal = $hasEquity ? $data->equityIncome : '0.00';
            $this->createElement($dom, $pozycje, 'P_28', $equityIncomeVal);
        }

        // --- Zawsze wymagana sekwencja: P_31, P_32, P_33 ---
        // P_31 = podstawa obliczenia podatku (TKwotaCNieujemna = integer)
        $this->createElement($dom, $pozycje, 'P_31', $this->formatInteger($data->equityTaxBase));
        // P_32 = stawka podatku (zawsze 19% dla art. 30b ust. 1)
        $this->createElement($dom, $pozycje, 'P_32', '19');
        // P_33 = podatek od dochodow art. 30b ust. 1 (TKwota2Nieujemna = decimal)
        $this->createElement($dom, $pozycje, 'P_33', $this->formatDecimal($data->equityTax));

        // P_35 = podatek nalezny art. 30b ust. 1 (opcjonalny, TKwotaCNieujemna = integer)
        if ($this->isNonZero($data->equityTax)) {
            $this->createElement($dom, $pozycje, 'P_35', $this->formatInteger($data->equityTax));
        }

        // --- Opcjonalna sekcja kryptowalut (art. 30b ust. 1a) ---
        // Cala grupa P_41/P_42/P_43 oraz podgrupa P_36/P_37/P_39|P_40 sa opcjonalne.
        // Emitujemy je tylko gdy sa przychody lub koszty z kryptowalut.
        $hasCrypto = $this->isNonZero($data->cryptoProceeds) || $this->isNonZero($data->cryptoCosts);
        if ($hasCrypto) {
            // Opcjonalna podgrupa P_36/P_37/P_39|P_40
            $this->createElement($dom, $pozycje, 'P_36', $data->cryptoProceeds);
            $this->createElement($dom, $pozycje, 'P_37', $data->cryptoCosts);

            if ($this->isNonZero($data->cryptoIncome)) {
                $this->createElement($dom, $pozycje, 'P_39', $data->cryptoIncome);
            } else {
                $this->createElement($dom, $pozycje, 'P_40', $data->cryptoLoss);
            }

            // Opcjonalna grupa P_41/P_42/P_43 (wszystkie trzy wymagane lacznie)
            // Emitujemy gdy jest podstawa lub podatek kryptowalutowy.
            $cryptoTaxBase = $this->deriveCryptoTaxBase($data);
            $this->createElement($dom, $pozycje, 'P_41', $this->formatInteger($cryptoTaxBase));
            $this->createElement($dom, $pozycje, 'P_42', '19');
            $this->createElement($dom, $pozycje, 'P_43', $this->formatDecimal($data->cryptoTax));

            // P_45 = podatek nalezny art. 30b ust. 1a (opcjonalny, integer)
            if ($this->isNonZero($data->cryptoTax)) {
                $this->createElement($dom, $pozycje, 'P_45', $this->formatInteger($data->cryptoTax));
            }
        }

        // --- Dywidendy zagraniczne (art. 30a ust. 1) ---
        // P_47 = brutto podatek polski = dividendTaxDue + dividendWHT
        if ($this->isNonZero($data->dividendTaxDue)) {
            $dividendGrossTax = $this->addAmounts($data->dividendTaxDue, $data->dividendWHT);
            $this->createElement($dom, $pozycje, 'P_47', $dividendGrossTax);

            if ($this->isNonZero($data->dividendWHT)) {
                $this->createElement($dom, $pozycje, 'P_48', $data->dividendWHT);
            }

            $this->createElement($dom, $pozycje, 'P_49', $data->dividendTaxDue);
        }

        // --- Zawsze wymagany wybor: P_51 (podatek do zaplaty) XOR P_52 (nadplata) ---
        $this->createElement($dom, $pozycje, 'P_51', $this->formatDecimal($data->totalTax));
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
     * Tworzy element w przestrzeni nazw ETD (DefinicjeTypy).
     * Uzywane dla elementow zdefiniowanych w TIdentyfikatorOsobyFizycznej1:
     * NIP, PESEL, ImiePierwsze, Nazwisko, DataUrodzenia.
     *
     * createTextNode() escapuje automatycznie znaki specjalne XML.
     */
    private function createEtdElement(\DOMDocument $dom, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $element = $dom->createElementNS(self::ETD_NAMESPACE_URI, $name);

        if ($value !== null) {
            $element->appendChild($dom->createTextNode($value));
        }

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
     * Formatuje wartosc jako liczbe calkowita (TKwotaCNieujemna = xsd:integer).
     * Usuwa czesc dziesietna — np. "1927.00" -> "1927", "10142" -> "10142".
     */
    private function formatInteger(string $value): string
    {
        return (string) (int) round((float) $value);
    }

    /**
     * Formatuje wartosc jako decimal z max 2 miejscami (TKwota2Nieujemna).
     * Gdy wartosc jest calkowita, zwraca bez .00 — XSD akceptuje "1927" jako xsd:decimal.
     * Gdy wartosc ma czesc dziesietna, zachowuje ja.
     */
    private function formatDecimal(string $value): string
    {
        // Jesli wartosc jest calkowita (brak czesc ulamkowej), zwroc jako integer string.
        // Uzywamy fmod zamiast == aby uniknac problemow ze strictowym porownaniem float vs int.
        $float = (float) $value;
        if (fmod($float, 1.0) === 0.0) {
            return (string) (int) $float;
        }

        return $value;
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

    /**
     * Wylicza podstawe podatku kryptowalutowego (P_41).
     * Gdy brak dochodow kryptowalutowych, podstawa = 0.
     */
    private function deriveCryptoTaxBase(PIT38Data $data): string
    {
        // Gdy jest podatek kryptowalutowy, podstawa = podatek / 0.19 (zaokraglona do pelnych zlotych).
        // W praktyce kalkulacja powinna byc dostarczona bezposrednio przez TaxCalc.
        // Jesli nie jest (zerowy podatek), zwracamy 0.
        if ($this->isNonZero($data->cryptoTax)) {
            // Podstawa = ceil(cryptoTax / 0.19) — jednak klient powinien dostarczyc gotowa wartosc.
            // Tu uzywamy cryptoIncome jako przyblizenia (identycznie jak equityTaxBase = equityIncome zaokraglone).
            return $this->isNonZero($data->cryptoIncome) ? $data->cryptoIncome : $data->cryptoTax;
        }

        return '0';
    }
}
