# Review Prawno-Podatkowy: TaxPilot

## Metadata

| | |
|---|---|
| **Data review** | 2026-04-02 |
| **Dokumenty przeanalizowane** | EVENT_STORMING.md, IMPLEMENTATION_PLAN.md, ARCHITECTURE.md, ADR-001 do ADR-011 |
| **Recenzenci** | Mec. Katarzyna Wisniewska (radca prawny, prawo podatkowe), Tomasz Kedzierski (doradca podatkowy, 300+ klientow) |

---

# CZESC I: Perspektywa prawna (Mec. Katarzyna Wisniewska)

## 1. Weryfikacja cytowanych przepisow

### FINDING-001: Art. 30a ust. 3 vs art. 24 ust. 10 -- FIFO

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: W EVENT_STORMING.md (zdarzenie #45) Tomasz wskazuje art. 30a ust. 3 jako podstawe FIFO. To jest bledny artykul. Art. 30a dotyczy zryczaltowanego podatku od dywidend i odsetek. Zasada FIFO dla papierow wartosciowych wynika z **art. 30b ust. 7 w zw. z art. 24 ust. 10 ustawy o PIT**. W dalszej czesci dokumentu (linia 1296) art. 24 ust. 10 jest juz prawidlowo cytowany. Ta niespojnosc moze prowadzic do blednego odwolania w kodzie.
- **Rekomendacja**: Ujednolicic we wszystkich dokumentach i w docblockach kodu -- FIFO = art. 24 ust. 10 ustawy o PIT (w zw. z art. 30b ust. 7). Usunac odwolanie do art. 30a ust. 3 w kontekscie FIFO.
- **Podstawa prawna**: art. 24 ust. 10, art. 30b ust. 7 ustawy z dnia 26 lipca 1991 r. o podatku dochodowym od osob fizycznych (Dz.U. 2024 poz. 226 t.j.)

### FINDING-002: Art. 17 ust. 1 pkt 6 vs art. 17 ust. 1 pkt 10

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: W dokumencie poprawnie cytowany jest art. 17 ust. 1 pkt 6 (przychody z odplatnego zbycia papierow wartosciowych) oraz art. 17 ust. 1 pkt 10 (przychody z instrumentow pochodnych). Jednak brakuje przywolania art. 17 ust. 1f (wylaczenie wymiany krypto-krypto), ktory jest kluczowy dla modulu kryptowalut. W EVENT_STORMING.md Tomasz mowi o "art. 17 ust. 1f" -- ten przepis jest poprawnie zidentyfikowany, ale nie jest cytowany w ADR-011 w kontekscie CryptoSeparationPolicy.
- **Rekomendacja**: W docbloku CryptoSeparationPolicy dodac pelne odwolanie do art. 17 ust. 1f ustawy o PIT (obowiazujacy od 1.01.2019).
- **Podstawa prawna**: art. 17 ust. 1f ustawy o PIT

### FINDING-003: Art. 30b ust. 5d -- koszyk kryptowalut

- **Priorytet**: P3 (nice to have)
- **Kto**: Prawnik
- **Opis**: W EVENT_STORMING.md (zdarzenie #71) i ADR-011 cytowany jest art. 30b ust. 5d jako podstawa odrebnego koszyka kryptowalut. To jest poprawne. Jednak nalezy doprecyzowac, ze chodzi o art. 30b ust. 5d-5g, ktore lacznie reguluja opodatkowanie walut wirtualnych (definicja przychodow, kosztow, zasady rozliczania). Sam ust. 5d to tylko czesc regulacji.
- **Rekomendacja**: Rozszerzyc odwolanie w docblockach na art. 30b ust. 5a-5g.
- **Podstawa prawna**: art. 30b ust. 5a-5g ustawy o PIT

### FINDING-004: Art. 22 ust. 1 pkt 38 -- koszty uzyskania przychodu

- **Priorytet**: P3 (nice to have)
- **Kto**: Prawnik
- **Opis**: Cytowanie jest poprawne. Art. 22 ust. 1 pkt 38 mowi o wydatkach na objecie lub nabycie papierow wartosciowych. Warto jednak dodac, ze dla kryptowalut analogiczna regulacja to art. 22 ust. 14-16 ustawy o PIT (wprowadzone od 2019). Dokumenty nie rozrozniaja tych podstaw.
- **Rekomendacja**: W polityce obliczania kosztow krypto dodac odwolanie do art. 22 ust. 14-16.
- **Podstawa prawna**: art. 22 ust. 14-16 ustawy o PIT

### FINDING-005: Art. 9 ust. 3 -- straty z lat poprzednich

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: Art. 9 ust. 3 jest poprawnie cytowany -- strata do odliczenia w ciagu 5 lat, max 50% rocznie. Jednak w ADR-011 w komentarzu do LossCarryForwardPolicy wspomina sie o "opcji jednorazowego odpisu do 5 mln PLN" -- ta opcja dotyczy wylacznie dzialalnosci gospodarczej (art. 9 ust. 3 pkt 2), NIE dochodow kapitalowych. W kodzie ta roznica jest poprawnie zaimplementowana (brak tej opcji), ale komentarz w ADR moze byc mylacy.
- **Rekomendacja**: Doprecyzowac w ADR-011, ze jednorazowy odpis do 5 mln PLN (art. 9 ust. 3 pkt 2) NIE dotyczy dochodow z kapitalow pienieznych (art. 30b). Usunac wzmiankowanie tego mechanizmu, aby nie wprowadzac w blad.
- **Podstawa prawna**: art. 9 ust. 3 pkt 1 i 2 ustawy o PIT

### FINDING-006: Art. 63 par. 1 Ordynacji podatkowej -- zaokraglanie

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: W ADR-006 i ADR-011 mowa o zaokraglaniu podatku "do pelnego zlotego w dol". To jest BLEDNE uproszczenie. Art. 63 par. 1 Ordynacji podatkowej mowi: "Podstawy opodatkowania (...) zaokragla sie do pelnych zlotych w ten sposob, ze **koncowki kwot wynoszace mniej niz 50 groszy pomija sie, a koncowki wynoszace 50 i wiecej groszy podwyzsza sie do pelnych zlotych**." To nie jest "zawsze w dol" -- to jest zaokraglanie matematyczne (>= 50 groszy = w gore, < 50 groszy = w dol). Dotyczy to PODSTAWY OPODATKOWANIA, nie podatku. Sam podatek jest wynikiem zastosowania stawki do zaokraglonej podstawy.
- **Rekomendacja**: Poprawic TaxRoundingPolicy: (1) Zaokraglic PODSTAWE OPODATKOWANIA do pelnych zlotych wg art. 63 par. 1 (>= 0.50 PLN w gore, < 0.50 PLN w dol). (2) Od zaokraglonej podstawy obliczyc podatek 19%. (3) Wynik (podatek) rowniez zaokraglic do pelnych zlotych wg tej samej reguly. Zaktualizowac docblock i testy.
- **Podstawa prawna**: art. 63 par. 1 ustawy z dnia 29 sierpnia 1997 r. -- Ordynacja podatkowa (Dz.U. 2023 poz. 2383 t.j.)

### FINDING-007: Art. 11a ust. 1 -- kurs NBP

- **Priorytet**: P3 (nice to have)
- **Kto**: Prawnik
- **Opis**: Poprawnie cytowany. Kurs sredni NBP z ostatniego dnia roboczego poprzedzajacego dzien uzyskania przychodu / poniesienia kosztu. Nalezy jednak zauwazyc, ze od 2024 r. w przypadku kryptowalut stosuje sie analogicznie art. 11a ust. 1, ale w praktyce pojawiaja sie watpliwosci co do "dnia uzyskania przychodu" przy transakcjach na gieldach crypto dzialajacych 24/7. To nie jest blad w dokumentacji, ale ryzyko interpretacyjne.
- **Rekomendacja**: Dodac ostrzezenie w systemie dla transakcji krypto realizowanych w weekendy / swieta -- kurs NBP moze wymagac cofniecia do piatku. Dodac komentarz w CurrencyConversionPolicy o tej specyfice.
- **Podstawa prawna**: art. 11a ust. 1 ustawy o PIT

---

## 2. Granica "narzedzie kalkulacyjne" vs. "doradztwo podatkowe"

### FINDING-008: Definicja granicy -- niewystarczajaco precyzyjna

- **Priorytet**: P0 (blocker)
- **Kto**: Prawnik
- **Opis**: Dokumenty poprawnie identyfikuja ten problem jako existential risk (HS-001). Mec. Wisniewska w EVENT_STORMING.md stwierdza, ze "obliczanie na podstawie danych uzytkownika i przepisow prawa to narzedzie kalkulacyjne". Jednak ta definicja jest niewystarczajaca z perspektywy art. 2 ust. 1 ustawy o doradztwie podatkowym. Czynnosci doradztwa obejmuja rowniez "prowadzenie ksiag podatkowych" (pkt 2) i "sporzadzanie zeznan podatkowych" (pkt 3). System TaxPilot de facto **sporządza zeznanie podatkowe** (generuje PIT-38 XML gotowy do zlozenia). To moze byc zakwalifikowane jako czynnosc doradztwa podatkowego w rozumieniu pkt 3, niezaleznie od tego czy system "doradza" czy "oblicza".
- **Rekomendacja**: Opinia prawna (G-001) MUSI jednoznacznie adresowac: czy generowanie gotowego PIT-38 XML (nie tylko obliczenie, ale gotowy formularz do zlozenia) wchodzi w zakres "sporzadzania zeznan podatkowych" w rozumieniu art. 2 ust. 1 pkt 3 ustawy o doradztwie podatkowym. Jesli tak -- rozwazyc model, w ktorym system generuje DANE do wypelnienia PIT-38, ale nie gotowy XML. Uzytkownik sam przepisuje do e-Deklaracji. Alternatywnie: wspolpraca z doradca podatkowym nadzorujacym proces (model whitelabel).
- **Podstawa prawna**: art. 2 ust. 1 pkt 1-3 ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym (Dz.U. 2020 poz. 130 t.j.)

### FINDING-009: Zdarzenie #117 -- "wykrycie potencjalnej optymalizacji podatkowej"

- **Priorytet**: P0 (blocker)
- **Kto**: Prawnik
- **Opis**: W EVENT_STORMING.md zdarzenie #117 mowi: "System wykryl potencjalna optymalizacje podatkowa (ale NIE zasugerowac)". Nawet jesli system "informuje" a nie "sugeruje" -- juz sam fakt analizowania sytuacji podatkowej uzytkownika pod katem optymalizacji wchodzi w zakres "udzielania porad i wyjasnien z zakresu obowiazkow podatkowych" (art. 2 ust. 1 pkt 1). Zastrzezenie "(ale NIE zasugerowac)" nie zmienia kwalifikacji prawnej -- istotna jest tresc komunikacji, nie jej forma.
- **Rekomendacja**: Calkowicie usunac zdarzenie #117 z zakresu systemu. System NIE powinien analizowac optymalizacji podatkowych w zadnej formie. Jesli uzytkownik chce optymalizacji -- eksport danych do doradcy podatkowego (US-022).
- **Podstawa prawna**: art. 2 ust. 1 pkt 1 ustawy o doradztwie podatkowym

### FINDING-010: Straty z lat poprzednich -- suwak wyboru kwoty

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: W przeplywie 5 (EVENT_STORMING.md) system "pokazuje mozliwosci" odpisu straty. Mec. Wisniewska poprawnie zauwazyc, ze pokazanie zakresu (0 do max) to informacja, nie doradztwo. Jednak jesli UI bedzie zawierac suwak z wartoscia domyslna (np. "max") lub podswietlenie "optymalnej" kwoty -- to jest doradztwo. Rowniez komunikat "Masz strate z 2021, ostatni rok na odliczenie" (EVENT_STORMING.md linia 806) -- to jest borderline. Informowanie o terminie przedawnienia prawa to informacja, ale w kontekscie "mozesz cos z tym zrobic" -- to kierunkowanie decyzji.
- **Rekomendacja**: (1) Suwak straty: wartosci domyslne = 0 (uzytkownik sam ustawia). (2) Brak podswietlenia "maksymalnej" opcji. (3) Komunikat o wygasaniu straty: dopuszczalny, ale w formie neutralnej -- "Strata z roku X wygasa w roku Y" bez sugestii dzialania. (4) Dodac disclaimer przy kazdym ekranie strat: "Decyzja o kwocie odpisu jest Twoja. W razie watpliwosci skonsultuj sie z doradca podatkowym."
- **Podstawa prawna**: art. 2 ust. 1 ustawy o doradztwie podatkowym

---

## 3. Disclaimer i regulamin

### FINDING-011: Disclaimer -- niewystarczajacy

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: W IMPLEMENTATION_PLAN.md (G-005) regulamin ma zawierac: "Aplikacja jest narzedziem kalkulacyjnym. Nie stanowi doradztwa podatkowego. Uzytkownik ponosi odpowiedzialnosc za poprawnosc danych." To jest absolutne minimum, ale niewystarczajace. Brakuje: (1) wyraźnego wylaczenia odpowiedzialnosci za poprawnosc obliczen, (2) wskazania ze wynik nie jest wiążacy prawnie, (3) rekomendacji konsultacji z doradca podatkowym, (4) informacji ze system opiera sie na algorytmach ktore moga zawierac bledy, (5) klauzuli dotyczącej aktualnosci przepisow.
- **Rekomendacja**: Regulamin powinien zawierac co najmniej:
  - "Wyniki obliczen maja charakter wylacznie informacyjny i pomagający."
  - "Aplikacja nie stanowi czynnosci doradztwa podatkowego w rozumieniu ustawy z dnia 5 lipca 1996 r."
  - "Operator nie ponosi odpowiedzialnosci za poprawnosc obliczen wynikajaca z bledow w algorytmach, danych wejsciowych lub zmiany przepisow."
  - "Uzytkownik jest wylacznym odpowiedzialnym za tresc skladanego zeznania podatkowego."
  - "Zalecamy weryfikacje wynikow przez doradce podatkowego przed zlozeniem zeznania."
  - "Przepisy podatkowe uwzglednione w systemie sa aktualne na dzien [data]. Operator nie gwarantuje natychmiastowej aktualizacji po nowelizacjach."
  - Klauzula arbitrazowa lub wskazanie sadu wlasciwego.
  - Informacja o ubezpieczeniu OC.
- **Podstawa prawna**: art. 384-385(4) Kodeksu cywilnego (wzorce umowne), art. 556-576 KC (rekojmia), Dyrektywa 2011/83/UE (prawa konsumenta)

### FINDING-012: Disclaimer in-app -- brak w specyfikacji UI

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: Definition of Done wymienia "Disclaimer widoczny (jesli dotyczy obliczen podatkowych)" ale nie precyzuje GDZIE i JAK. Disclaimer tylko w regulaminie (ktory nikt nie czyta) jest niewystarczajacy. Musi byc widoczny w kontekscie uzytkowania.
- **Rekomendacja**: Disclaimer musi pojawiac sie: (1) Na kazdym ekranie z wynikami obliczen (footer). (2) Przed eksportem PIT-38 XML (modal z wymaganym potwierdzeniem "Rozumiem, ze ten plik wymaga mojej weryfikacji"). (3) Na ekranie PIT-38 preview (banner). (4) Przy kazdym odpisie straty. Wymagac akceptacji disclaimera PRZED pierwszym eksportem (nie tylko akceptacji regulaminu przy rejestracji).
- **Podstawa prawna**: art. 8 Dyrektywy 2011/83/UE

---

## 4. Odpowiedzialnosc prawna

### FINDING-013: Odpowiedzialnosc cywilna -- niedostatecznie zaadresowana

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: Dokumenty identyfikuja ryzyko R-002 (bledne obliczenie -> kara US -> pozew). Mitygacja: regulamin + OC + audit trail. To jest poprawny kierunek, ale niekompletny. Brakuje analizy: (1) Czy klauzula wylaczenia odpowiedzialnosci w regulaminie jest skuteczna wobec konsumenta (art. 385(1) KC -- klauzule niedozwolone). (2) Odpowiedzialnosc deliktowa (art. 415 KC) nie moze byc wylaczona umownie. (3) Jesli system "sporządza zeznanie" (patrz FINDING-008), to odpowiedzialnosc moze byc szersza.
- **Rekomendacja**: (1) Opinia prawna musi ocenic skutecznosc klauzuli wylaczenia odpowiedzialnosci wobec konsumentow. (2) OC musi obejmowac odpowiedzialnosc za bledne obliczenia (nie tylko "narzedzie IT" ale "narzedzie kalkulacyjne dla celow podatkowych"). (3) Rozwazyc limit odpowiedzialnosci (np. do wartosci ubezpieczenia OC). (4) Rozwazyc obligatoryjny screen "Zweryfikuj wynik przed zlozeniem" z checklistą.
- **Podstawa prawna**: art. 385(1) KC, art. 415 KC, art. 471 KC

### FINDING-014: Brak polityki refundow w kontekscie blednych obliczen

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: Zdarzenie #110 (zadanie zwrotu pieniedzy) jest zidentyfikowane, ale brak polityki: co jesli uzytkownik udowodni ze obliczenie bylo bledne i poniosl szkode? Regulamin "uzytkownik ponosi odpowiedzialnosc" nie wystarczy jesli blad jest po stronie systemu.
- **Rekomendacja**: Opracowac procedure reklamacyjna: (1) Uzytkownik zglasza blad. (2) System weryfikuje (porownanie z golden dataset lub reczna weryfikacja). (3) Jesli blad potwierdzony: zwrot oplaty + pomoc w korekcie PIT-38 + informacja o mozliwosci czynnego zalu (art. 16 KKS). (4) Jesli szkoda > oplata: OC.
- **Podstawa prawna**: art. 16 KKS, art. 81 Ordynacji podatkowej (korekta)

---

## 5. GDPR

### FINDING-015: GDPR -- analiza niepelna

- **Priorytet**: P1 (must fix)
- **Kto**: Prawnik
- **Opis**: HS-014 poprawnie identyfikuje konflikt miedzy prawem do usuniecia (art. 17 GDPR) a obowiazkiem archiwizacji. Mec. Wisniewska wskazuje art. 17 ust. 3 lit. b jako wyjątek. Jednak analiza jest niepelna. Brakuje:
  - **Podstawa prawna przetwarzania**: Jaka jest podstawa? Art. 6 ust. 1 lit. a (zgoda) czy lit. b (wykonanie umowy) czy lit. f (prawnie uzasadniony interes)? Dla danych finansowych -- prawdopodobnie lit. b (swiadczenie uslugi) + lit. c (obowiazek prawny -- w zakresie archiwizacji).
  - **Okres retencji**: Ile lat przechowujemy dane? 5 lat (Ordynacja podatkowa)? A potem?
  - **Prawa osoby**: Prawo dostepu (art. 15), prawo do przenoszenia (art. 20), prawo do sprostowania (art. 16) -- nie wymienione w dokumentach.
  - **DPIA**: Czy wymagana jest ocena skutkow (art. 35 GDPR)? Przetwarzanie danych finansowych na duza skale -- prawdopodobnie TAK.
  - **Transfer danych**: AWS to podmiot amerykanski. Czy jest umowa o powierzeniu (DPA)? Czy stosowane sa SCC?
  - **DPO**: Czy wymagany jest IOD? Prawdopodobnie nie (nie jestesmy organem publicznym i nie przetwarzamy na "duza skale" w rozumieniu art. 37) -- ale warto rozwazyc dobrowolne powolanie.
- **Rekomendacja**: Przed uruchomieniem wymagane: (1) Rejestr czynnosci przetwarzania (art. 30). (2) Polityka prywatnosci zgodna z art. 13/14 GDPR. (3) Umowa powierzenia z AWS (DPA). (4) Analiza koniecznosci DPIA. (5) Polityka retencji danych: dane transakcyjne 5 lat od konca roku podatkowego, potem automatyczne usuniecie. (6) Mechanizm eksportu danych (art. 20 -- data portability).
- **Podstawa prawna**: art. 6, 13, 14, 15, 16, 17, 20, 30, 35, 37 GDPR

### FINDING-016: NIP jako dane osobowe -- szyfrowanie niewystarczajace

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: NIP jest identyfikatorem podatkowym i kwalifikuje sie jako dana osobowa. Michał P. poprawnie wskazuje szyfrowanie column-level i hashowanie do wyszukiwania. Jednak: (1) Czy NIP jest w ogole POTRZEBNY w systemie? System oblicza podatek -- do tego NIP nie jest wymagany. NIP jest potrzebny dopiero w PIT-38 XML. (2) Zasada minimalizacji danych (art. 5 ust. 1 lit. c GDPR) -- nie zbieraj wiecej niz potrzebujesz.
- **Rekomendacja**: Rozwazyc model: NIP podawany TYLKO na etapie generowania PIT-38 XML, przetrzymywany wylacznie w pamieci (nie w bazie), wstawiany do XML i zapominany. Jesli jest potrzebny do archiwum -- przechowywac zaszyfrowany z kluczem uzytkownika (nie systemowym).
- **Podstawa prawna**: art. 5 ust. 1 lit. c GDPR (minimalizacja danych)

---

## 6. Ryzyko naruszenia ustawy o doradztwie podatkowym

### FINDING-017: Funkcjonalnosc "Porownianie z PIT-8C" -- potencjalne doradztwo

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: US-015 (porownanie z PIT-8C) przewiduje "wyjasnienie mozliwych przyczyn roznicy". Jesli system mowi uzytkownikowi DLACZEGO jego obliczenie rozni sie od PIT-8C i ktore jest "poprawne" -- to jest udzielanie wyjasnien z zakresu obowiazkow podatkowych (art. 2 ust. 1 pkt 1).
- **Rekomendacja**: Porownanie: TAK (zestawienie kwot). Wyjaśnianie przyczyn roznic: NIE. Zamiast "Roznica wynika z tego, ze broker nie uwzglednil prowizji" -> "Wykryto roznice. Skonsultuj sie z doradca podatkowym."
- **Podstawa prawna**: art. 2 ust. 1 pkt 1 ustawy o doradztwie podatkowym

---

## 7. Czego brakuje z perspektywy prawnej

### FINDING-018: Brak analizy e-commerce / swiadczenia uslug droga elektroniczna

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: TaxPilot to usluga swiadczona droga elektroniczna w rozumieniu ustawy z dnia 18 lipca 2002 r. Dokumenty nie adresuja: (1) Obowiazku rejestracji w CEIDG/KRS (zaplanowane -- G-005). (2) Regulaminu swiadczenia uslug droga elektroniczna (art. 8 ustawy). (3) Obowiazku informacyjnego wobec konsumentow (art. 12 ustawy o prawach konsumenta). (4) Prawa odstapienia od umowy w 14 dni (art. 27 ustawy o prawach konsumenta) -- to istotne przy subskrypcji. (5) Faktury VAT za subskrypcje.
- **Rekomendacja**: Dodac do pre-development gate: regulamin swiadczenia uslug droga elektroniczna zgodny z ustawa. Doprecyzowac prawo odstapienia (czy dotyczy uslugi juz wykonanej?). Zapewnic integracje z systemem fakturowym.
- **Podstawa prawna**: Ustawa z dnia 18 lipca 2002 r. o swiadczeniu uslug droga elektroniczna, Ustawa z dnia 30 maja 2014 r. o prawach konsumenta

### FINDING-019: Brak regulacji praw autorskich do danych brokerow

- **Priorytet**: P2 (should fix)
- **Kto**: Prawnik
- **Opis**: Ryzyko R-012 identyfikuje mozliwosc, ze broker zabroni uzywania jego danych. Jednak nie ma analizy: czy format CSV brokera jest chroniony prawem autorskim? Czy Terms of Service brokerow (IBKR, Degiro, XTB) zabraniaja automatycznego przetwarzania danych? Czy web scraping (nieoficjalne API Degiro) jest legalny?
- **Rekomendacja**: Przed uruchomieniem: (1) Przeanalizowac ToS kazdego brokera pod katem uzywania ich danych. (2) Jesli ToS zabrania -- CSV upload przez uzytkownika (nie scraping) jest bezpieczniejszy (uzytkownik eksportuje swoje dane). (3) Dodac w regulaminie: "Uzytkownik potwierdza, ze ma prawo do udostepnienia danych ze swoich kont brokerskich."
- **Podstawa prawna**: art. 1 ustawy o prawie autorskim, art. 6-7 ustawy o ochronie baz danych

---

# CZESC II: Perspektywa doradcy podatkowego (Tomasz Kedzierski)

## 1. Weryfikacja regul podatkowych w ADR-011

### FINDING-020: Stawka podatku -- poprawna

- **Priorytet**: N/A (OK)
- **Kto**: Doradca podatkowy
- **Opis**: 19% stawka podatku od dochodow kapitalowych (art. 30b ust. 1) -- poprawna. Nie zmieniala sie od 2004 roku. Brak planow zmian na 2026/2027.
- **Rekomendacja**: Brak. Poprawne.

### FINDING-021: FIFO -- poprawna implementacja, ale brak obslugi wyjatkow

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: Algorytm FIFO w ARCHITECTURE.md jest poprawnie zaimplementowany dla standardowego scenariusza. Cross-broker FIFO per ISIN -- poprawnie. Jednak brakuje obslugi nastepujacych wyjatkow: (1) **Akcje nabyte w drodze darowizny** -- koszt nabycia = koszt u darczyńcy, nie cena rynkowa z dnia darowizny (art. 22 ust. 1d). (2) **Akcje nabyte w drodze spadku** -- koszt nabycia = wartosc rynkowa z dnia nabycia spadku (art. 22 ust. 1m). HS-023 to identyfikuje, ale jest OPEN. (3) **Akcje z konwersji PDA** -- koszt nabycia = cena zaplacona za PDA. (4) **Split/reverse split** -- poprawnie zidentyfikowane jako nie-zdarzenie podatkowe. ALE: reverse split z wyplaceniem ulomka (cash-in-lieu) JEST zdarzeniem podatkowym. (5) **Spin-off** -- HS-009 identyfikuje problem, ale brak rozwiazania. W praktyce alokacja kosztu nabycia jest proporcjonalna do cen rynkowych w dniu spin-off.
- **Rekomendacja**: (1) Dla v1: dodac mozliwosc recznego ustawienia kosztu nabycia (manual override) dla pozycji nabytych darem/spadkiem/PDA. (2) Dla v2: zautomatyzowac obliczanie kosztu spadku/darowizny. (3) Reverse split z cash-in-lieu: traktowac wyplate ulomka jako sprzedaz. (4) Spin-off: wymagac recznego podania proporcji alokacji (system moze zaproponowac na podstawie cen zamkniecia, ale uzytkownik potwierdza).
- **Podstawa prawna**: art. 22 ust. 1d, 1m ustawy o PIT

### FINDING-022: Limit 50% straty -- brakuje walidacji krzyżowej

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: LossCarryForwardPolicy poprawnie ogranicza odpis do 50% straty. Jednak nie weryfikuje, czy uzytkownik nie odliczyl juz czesci tej straty w POPRZEDNICH LATACH (poza systemem). Jesli uzytkownik po raz pierwszy korzysta z TaxPilot w 2026 i ma strate z 2023, system nie wie ile odliczyl w 2024 i 2025.
- **Rekomendacja**: Uzytkownik MUSI podac: (1) Kwote oryginalnej straty. (2) Kwote juz odliczona w poprzednich latach. System oblicza remainingAmount = oryginalna - juz_odliczona. Walidacja: suma odliczen ze wszystkich lat <= 100% straty. To jest juz czesciowo zaadresowane (PriorYearLoss.remainingAmount), ale wymaga jawnego input od uzytkownika i walidacji.
- **Podstawa prawna**: art. 9 ust. 3 ustawy o PIT

### FINDING-023: Koszyk CFD -- poprawnosc laczenia

- **Priorytet**: P2 (should fix)
- **Kto**: Doradca podatkowy
- **Opis**: Tabela koszykow podatkowych (EVENT_STORMING.md) mowi "Akcje + CFD = TAK (oba w sekcji C PIT-38)". To jest POPRAWNE -- zyski i straty z akcji i instrumentow pochodnych moga sie kompensowac w ramach sekcji C PIT-38. Jednak warto dodac: zyski z zamkniecia pozycji CFD to przychody z instrumentow pochodnych (art. 17 ust. 1 pkt 10), a koszt to wydatki na nabycie (art. 23 ust. 1 pkt 38a). Sa w tym samym koszyku, ale PODSTAWA PRAWNA jest inna niz dla akcji.
- **Rekomendacja**: W BasketCombinationMatrix dodac komentarz o roznej podstawie prawnej (art. 17 ust. 1 pkt 6 vs pkt 10) -- dla celow audit trail i potencjalnej zmiany przepisow.
- **Podstawa prawna**: art. 17 ust. 1 pkt 6 i pkt 10 ustawy o PIT

---

## 2. Tabela UPO -- weryfikacja stawek WHT

### FINDING-024: Stawki WHT -- w wiekszosci poprawne, z zastrzezeniami

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: Tabela UPO z EVENT_STORMING.md (Mec. Nowak):

  | Kraj | Stawka w dokumencie | Moja weryfikacja | Status |
  |---|---|---|---|
  | USA | 15% | Poprawne (art. 11 ust. 2 UPO PL-US, z W-8BEN) | OK |
  | UK | 10% | **BLEDNE**. UPO PL-UK (art. 10 ust. 2) przewiduje 10% DLA UDZIALOW >= 10%. Dla mniejszosciowych udzialow (< 10%) = **15%**. Wiekszosc indywidualnych inwestorow ma < 10%, wiec stawka powinna byc **15%**, nie 10%. | BLAD |
  | Niemcy | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-DE). Solidaritaetszuschlag jest podatkiem wewnetrznym -- nie wchodzi do stawki UPO. | OK ale uwaga |
  | Irlandia | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-IE) | OK |
  | Holandia | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-NL) | OK |
  | Szwajcaria | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-CH) | OK |
  | Kanada | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-CA) | OK |
  | Japonia | 10% | Poprawne (art. 10 ust. 2 lit. b UPO PL-JP) | OK |
  | Australia | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-AU) | OK |
  | Luksemburg | 15% | Poprawne (art. 10 ust. 2 lit. b UPO PL-LU) | OK |

- **Rekomendacja**: (1) Poprawic stawke UK na 15% dla indywidualnych inwestorow (< 10% udzialow). (2) Dodac kolumne "warunek" do tabeli UPO (procent udzialow, typ inwestora). (3) Dla Niemiec: dodac uwage ze Solidaritaetszuschlag (5,5% podatku) jest de facto potrącany -- efektywna stawka to ok. 26,375%, ale w UPO liczy sie 15%. Roznica = nadplata za granicą, nieodzyskiwalna w PL. (4) Uzupelnic tabele o brakujace kraje popularne wsrod inwestorow: **Francja** (15% UPO), **Dania** (15% UPO ale efektywna 27%), **Norwegia** (15% UPO), **Finlandia** (15% UPO), **Hong Kong** (0% -- brak WHT), **Singapur** (0% -- brak WHT na dywidendach).
- **Podstawa prawna**: Poszczegolne UPO (dostepne na stronie MF: podatki.gov.pl)

---

## 3. Klasyfikacja instrumentow

### FINDING-025: Klasyfikacja -- niekompletna

- **Priorytet**: P2 (should fix)
- **Kto**: Doradca podatkowy
- **Opis**: Zdarzenia #26-#36 wymieniaja: akcje, CFD, kryptowaluty, ETF, obligacje, opcje/warranty, prawa poboru, PDA. Brakuje:
  - **Certyfikaty inwestycyjne** (fundusze zamkniete -- FIZ) -- czeste w IBKR
  - **Jednostki uczestnictwa** (fundusze otwarte -- FIO/SFIO) -- jesli nabycie przez brokera zagranicznego
  - **Kontrakty futures** -- popularne na CME, czesto handlowane przez IBKR
  - **ADR/GDR** -- kwity depozytowe, np. ADR na Alibabe (BABA). Podatkowo = papier wartosciowy jak akcja, ALE: dywidenda z ADR ma inny lancuch UPO (emitent w Chinach, ADR w USA -- ktore UPO stosowac? PL-CN czy PL-US?)
  - **SPAC** -- special purpose acquisition company. Przed polaczeniem: papier wartosciowy. Po polaczeniu: moze byc spin-off + merger.
  - **Fractional shares** -- OQ-010 jest OPEN. To jest KRYTYCZNE -- Trading212 i IBKR oferuja ulamkowe akcje. FIFO na 0.37 akcji jest legalny, ale implementacja musi dzialac na BigDecimal.
- **Rekomendacja**: (1) Dodac ADR/GDR z uwaga o lancuchu UPO. (2) Dodac futures (instrumenty pochodne, koszyk jak CFD). (3) Fractional shares: potwierdzic ze BigDecimal obsluguje ilosci < 1.0 i dodac testy. (4) Dla v1: brakujace typy -> manual override klasyfikacji przez uzytkownika.
- **Podstawa prawna**: art. 5a pkt 11 ustawy o PIT (definicja papierow wartosciowych)

---

## 4. Edge cases z Event Storming -- ocena kompletnosci

### FINDING-026: Brakujace edge cases z praktyki

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: Z moich 300+ klientow rocznie -- nastepujace sytuacje NIE sa zaadresowane w dokumentach, a wystepuja regularnie:

  1. **Zmiana rezydencji podatkowej w trakcie roku** -- klient przeprowadza sie do Polski w marcu, ma transakcje ze stycznia-lutego gdy byl nierezydentem. System zaklada polskiego rezydenta -- nie obsluguje partial-year residency.

  2. **Darowizna akcji** -- nie ma w scope. Darowizna NIE jest zdarzeniem podatkowym (brak przychodu u obdarowanego). Ale koszt nabycia przejmuje sie od darczyncy. Jesli klient dostal akcje w darowiznie i je sprzedaje -- musi podac koszt darczyncy.

  3. **Split walutowy** -- klient ma USD, kupuje akcje za EUR na Xetrze przez IBKR. IBKR automatycznie przewalutowuje USD->EUR. Ta konwersja walutowa GENERUJE przychod/koszt podatkowy (roznice kursowe z art. 24c ustawy o PIT) -- ale to dotyczy dzialalnosci gospodarczej. Dla osoby fizycznej nieprowadzacej DG -- te roznice kursowe nie sa opodatkowane. ALE: koszt nabycia akcji jest w EUR, przeliczony kursem NBP z dnia-1.

  4. **Margin call / forced liquidation** -- broker zamyka pozycje przymusowo. To jest normalna sprzedaz podatkowo, ale klient moze nie miec swiadomosci ze to zdarzenie podatkowe.

  5. **Dywidenda reinwestowana automatycznie (DRIP)** -- dywidenda jest przychodem (PIT-38 sekcja D lub ryczalt). Reinwestycja = nowy zakup. Brak zdarzenia w EVENT_STORMING.md.

  6. **Return of capital** -- nie jest dywidenda. Zmniejsza koszt nabycia, nie generuje przychodu. Czeste w REIT-ach i funduszach zamknietych.

  7. **Konwersja ADR na akcje (i odwrotnie)** -- nie jest zdarzeniem podatkowym (ten sam instrument ekonomicznie), ale w danych brokera wyglada jak sprzedaz ADR + kupno akcji.

  8. **Tax-free w IKE/IKZE** -- jesli klient ma konto IKE/IKZE u zagranicznego brokera (co jest mozliwe prawnie, choc rzadkie) -- transakcje na IKE sa zwolnione z podatku. System nie obsluguje wylaczen.

- **Rekomendacja**: (1) Dla v1: dodac manual override "Ta transakcja nie jest zdarzeniem podatkowym" (pokrywa konwersje ADR, transfer miedzy brokerami, IKE). (2) Dla v1: dodac manual override kosztu nabycia (pokrywa darowizny, spadki, ESOP). (3) Dla v2: DRIP, return of capital, forced liquidation. (4) Partial-year residency: out of scope v1, ale jasny komunikat "System zaklada rezydencje podatkowa w Polsce przez caly rok podatkowy."
- **Podstawa prawna**: rozne (art. 22 ust. 1d, art. 24c, art. 52a ustawy o PIT)

---

## 5. Golden dataset -- ocena scenariuszy

### FINDING-027: Golden dataset -- dobry, ale brakuje kluczowych scenariuszy

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: 20 scenariuszy w ADR-008 jest solidnych. Jednak brakuje:

  | # | Brakujacy scenariusz | Dlaczego wazny |
  |---|---|---|
  | 21 | Dywidenda z USA BEZ W-8BEN (30% WHT) | Czesty blad inwestorow -- nadplata WHT. Dopłata w PL = 0, ale 30%-19%=11% przepada. |
  | 22 | Multi-currency FIFO: ten sam ISIN, kupno za USD, sprzedaz za EUR | Dual-listing problem (HS-011). Kurs NBP inny dla USD i EUR. |
  | 23 | Strata z krypto + zysk z akcji w tym samym roku | Weryfikacja ze koszyki sie NIE lacza. |
  | 24 | FIFO z otwartą pozycja (kupno w 2024, brak sprzedazy w 2025) | Weryfikacja ze otwarte pozycje nie generuja przychodu. |
  | 25 | Dywidenda w walucie innej niz waluta instrumentu | Np. akcja szwajcarska wyplacajaca dywidende w CHF, ale notowana na Xetrze w EUR. |
  | 26 | Wielokrotne czesciowe sprzedaze z jednej transakcji kupna | Buy 1000 akcji. Sell 100. Sell 200. Sell 300. Sell 400. Weryfikacja FIFO na kazdym kroku. |
  | 27 | Fractional shares: sprzedaz 0.5 akcji | Kluczowe dla Trading212 / IBKR. |
  | 28 | Rok z samymi dywidendami zagranicznymi (bez zyskow kapitalowych) | PIT-38 z pusta sekcja C, wypelniona sekcja D. |

- **Rekomendacja**: Rozszerzyc golden dataset do 28 scenariuszy. Scenariusze 21, 23, 26 sa P1. Pozostale P2.

---

## 6. Overnight fee w CFD (HS-003)

### FINDING-028: ASK_USER jako default -- poprawna decyzja

- **Priorytet**: P3 (nice to have)
- **Kto**: Doradca podatkowy
- **Opis**: Decyzja w ADR-011 (SwapFeeStrategy z domyslnym ASK_USER) jest poprawna. W mojej praktyce wiekszosc US akceptuje overnight fee jako koszt uzyskania przychodu (analogia do odsetek od debet na rachunku maklerskim). Jednak istnieja US ktore odmawiaja. Brak jednoznacznej interpretacji ogolnej MF.
- **Rekomendacja**: (1) ASK_USER z ostrzezeniem: "Traktowanie overnight fee jako kosztu jest kontrowersyjne. Czesc Urzedow Skarbowych akceptuje to jako koszt, czesc nie. W razie watpliwosci skonsultuj sie z doradca podatkowym lub wystąp o indywidualna interpretacje podatkowa." (2) Dodac link do przykladowych interpretacji (IPPB1/415-123/14-2/AM i podobne). (3) Zapisywac wybor uzytkownika w audit trail.

---

## 7. Kryptowaluty

### FINDING-029: Wymiana krypto-krypto -- poprawna

- **Priorytet**: N/A (OK)
- **Kto**: Doradca podatkowy
- **Opis**: Poprawnie zidentyfikowane: od 1.01.2019 wymiana krypto na krypto NIE jest zdarzeniem podatkowym (art. 17 ust. 1f). Dotyczy rowniez wymiany krypto na stablecoina (USDT, USDC) -- to jest krypto-na-krypto.
- **Rekomendacja**: Brak. Poprawne. Dodac testy na wymiane BTC->USDT->ETH (zadna z tych wymian nie jest zdarzeniem).

### FINDING-030: Staking -- moment rozpoznania przychodu

- **Priorytet**: P2 (should fix)
- **Kto**: Doradca podatkowy
- **Opis**: HS-016 poprawnie identyfikuje problem. W mojej praktyce: przychod z tytulu stakingu rozpoznaje sie w momencie, gdy nagroda zostaje udostepniona podatnikowi (credited to wallet). Jesli nagroda jest zablokowana (locked staking, np. ETH do niedawna) -- argumentuje sie, ze przychod powstaje dopiero w momencie odblokowania (unstaking), poniewaz podatnik nie ma dyspozycji srodkami (art. 11 ust. 1 ustawy o PIT -- przychod = otrzymane lub postawione do dyspozycji).
- **Rekomendacja**: Dla v1 (krypto out of scope) -- nie dotyczy. Dla v2: domyslnie traktowac credited-to-wallet jako moment przychodu, z opcja manual override. Dodac disclaimer o braku jednoznacznej interpretacji MF.
- **Podstawa prawna**: art. 11 ust. 1, art. 17 ust. 1f ustawy o PIT

### FINDING-031: Airdropy i mining -- wycena

- **Priorytet**: P2 (should fix)
- **Kto**: Doradca podatkowy
- **Opis**: Zdarzenie #68 identyfikuje problem wyceny airdropu. W praktyce: wartosc rynkowa z momentu otrzymania (cena na gieldzie). Jesli airdrop nie ma ceny rynkowej (np. nowy token bez listingu) -- wartosc = 0 do momentu pierwszego notowania. Mining: koszt energii i sprzetu NIE jest kosztem uzyskania przychodu z kapitalow pienieznych (chyba ze dzialalnosc gospodarcza).
- **Rekomendacja**: Dla v2/v3. Dodac uwage w dokumentacji.

---

## 8. Najwieksze ryzyka poprawnosci obliczen

### FINDING-032: Zaokraglanie -- NAJWYZSZE RYZYKO

- **Priorytet**: P0 (blocker)
- **Kto**: Doradca podatkowy
- **Opis**: Bledne zaokraglanie (FINDING-006) to najwyzsze ryzyko. Jesli system zaokragla podstawe opodatkowania "w dol" zamiast wg art. 63 par. 1 -- wynik moze sie roznic o 1 PLN podatku. Przy 50 000 uzytkownikach to 50 000 potencjalnie blednych zeznan. US porownuje do grosza.
- **Rekomendacja**: Natychmiastowa poprawka TaxRoundingPolicy + test w golden dataset porownujacy zaokraglanie z recznym obliczeniem.
- **Podstawa prawna**: art. 63 par. 1 Ordynacji podatkowej

### FINDING-033: Prowizja -- alokacja przy czesciowej sprzedazy

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: W ARCHITECTURE.md prowizja kupna jest alokowana proporcjonalnie do ilosci: `buyCommissionPLN = oldest.commissionPLN.amount() * matchQuantity / oldest.remainingQuantity`. To jest prawidlowe podejscie. JEDNAK: prowizja sell rowniez jest alokowana proporcjonalnie (`commissionPerUnit = sellCommissionPLN / quantity`). Problem: jesli sprzedaz 100 akcji matchuje z 3 roznymi zakupami (FIFO: 50 + 30 + 20), to prowizja sell powinna byc rozdzielona proporcjonalnie do ilosci w kazdym matchu (50/100, 30/100, 20/100). Kod to robi poprawnie. **ALE**: zaokraglanie prowizji na kazdym matchu moze prowadzic do utraty/nadmiaru groszy. Suma zaokraglonych prowizji moze nie rownac sie oryginalnej prowizji.
- **Rekomendacja**: Dodac asercje: suma buy_commission_PLN + sell_commission_PLN ze wszystkich matchow z jednej transakcji sell == calkowita prowizja sell w PLN. Jesli nie -- rozdzielić roznice na ostatni match (adjustment). Dodac test property-based na te wlasciwosc.
- **Podstawa prawna**: art. 22 ust. 1 pkt 38 ustawy o PIT

### FINDING-034: Kurs NBP -- ryzyko edge case weekendowego

- **Priorytet**: P1 (must fix)
- **Kto**: Doradca podatkowy
- **Opis**: System poprawnie identyfikuje "ostatni dzien roboczy PRZED transakcja". Ale: (1) Transakcja w poniedzialek -- kurs z piatku. OK. (2) Transakcja w poniedzialek bedzacy swietem (np. 1 maja) -- kurs z piatku. OK. (3) Transakcja w piatek wieczorem w USA (23:00 NYC = sobota 5:00 Warszawa) -- jaki dzien? Jesli broker raportuje piatek -- kurs z czwartku. Jesli system interpretuje jako sobote PL -- kurs z piatku. Roznica moze byc istotna przy duzych wolumenach.
- **Rekomendacja**: Zasada: data transakcji = data z raportu brokera (nie przeliczana na czas PL). Kurs NBP = ostatni dzien roboczy PRZED ta data. Tzn. jesli broker mowi "2025-09-19 (piatek)" -- kurs z 2025-09-18 (czwartek). Dodac testy na: piatek, sobota (NBP nie publikuje), swiety, 1 stycznia.
- **Podstawa prawna**: art. 11a ust. 1 ustawy o PIT

---

# PODSUMOWANIE

## Statystyka findings

| Priorytet | Ilosc | Opis |
|---|---|---|
| P0 (blocker) | 3 | FINDING-006 (zaokraglanie), FINDING-008 (doradztwo vs narzedzie), FINDING-009 (optymalizacja podatkowa) |
| P1 (must fix) | 13 | FINDING-001, 010, 011, 012, 013, 015, 021, 022, 024, 026, 027, 033, 034 |
| P2 (should fix) | 10 | FINDING-002, 005, 014, 016, 017, 018, 019, 023, 025, 030 |
| P3 (nice to have) | 4 | FINDING-003, 004, 007, 028 |
| OK (brak uwag) | 3 | FINDING-020, 029, brak |

## Czy projekt jest gotowy do implementacji?

### Odpowiedz: WARUNKOWO TAK, pod warunkiem spelnienia ponizszych wymagan.

### Warunki P0 (MUSZA byc spelnione PRZED rozpoczeciem prac developerskich):

1. **Zaokraglanie (FINDING-006, FINDING-032)**: Poprawic TaxRoundingPolicy na zgodna z art. 63 par. 1 Ordynacji podatkowej (zaokraglanie matematyczne, nie "w dol"). To jest 30-minutowa poprawka kodu, ale ma impact na KAZDE obliczenie.

2. **Opinia prawna ws. doradztwa (FINDING-008)**: Opinia MUSI jednoznacznie odpowiedziec na pytanie: "Czy generowanie gotowego PIT-38 XML to sporzadzanie zeznania podatkowego w rozumieniu art. 2 ust. 1 pkt 3 ustawy o doradztwie podatkowym?" Jesli TAK -- konieczna zmiana modelu (dane zamiast gotowego XML, lub wspolpraca z doradca).

3. **Usuniecie zdarzenia #117 (FINDING-009)**: System NIE moze analizowac optymalizacji podatkowych. Usunac z zakresu produktu calkowicie.

### Warunki P1 (MUSZA byc spelnione PRZED pierwszym release publicznym):

4. Poprawka stawki WHT UK na 15% (FINDING-024).
5. Disclaimer in-app na kazdym ekranie obliczen (FINDING-011, FINDING-012).
6. Pelna analiza GDPR z rejestrem czynnosci przetwarzania (FINDING-015).
7. Manual override kosztu nabycia dla spadkow/darowizn (FINDING-021).
8. Walidacja krzyzowa strat z lat poprzednich (FINDING-022).
9. Rozszerzenie golden dataset o scenariusze 21, 23, 26 (FINDING-027).
10. Test asercji sum prowizji (FINDING-033).
11. Jednoznaczna zasada timezone dla kursu NBP (FINDING-034).

### Ogolna ocena projektu:

Dokumentacja jest na bardzo wysokim poziomie. Event Storming trafnie zidentyfikowal kluczowe ryzyka. Architektura (modular monolith, Clean Architecture, CQRS) jest wlasciwym wyborem dla tego typu systemu. Podejscie TDD z golden dataset od doradcy podatkowego jest wzorcowe.

Glowne zagrozone obszary to:
- **Granica doradztwo/narzedzie** -- wymaga rozstrzygniecia prawnego przed jakimkolwiek kodem.
- **Zaokraglanie** -- drobny blad z duzym impactem.
- **Kompletnosc edge cases** -- konieczne rozszerzenie testow.

Jesli trzy warunki P0 zostana spelnione, projekt moze wejsc w faze Walking Skeleton (tydzien 3-6) z czystym sumieniem prawnym i podatkowym.

---

*Dokument przygotowany przez Mec. Katarzyne Wisniewska i Tomasza Kedzierskiego w ramach review dokumentacji projektowej TaxPilot.*
*Data: 2026-04-02*
