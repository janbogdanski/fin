---
title: "PIT-38 za 2026 — terminy, zmiany, nowe przepisy"
slug: pit-38-termin-2027
description: "PIT-38 za 2026 rok: termin składania (30 kwietnia 2027), zmiany w przepisach, nowe progi, Twój e-PIT vs własny XML, kary za spóźnienie, korekta deklaracji."
date: 2026-12-22
keywords: [pit-38 termin skladania 2027, pit-38 za 2026 termin, pit-38 zmiany 2027, pit-38 kara za spoznienie, pit-38 korekta]
schema: Article
---

# PIT-38 za 2026 — terminy, zmiany, nowe przepisy

Sezon podatkowy 2027 zbliża się wielkimi krokami. Jeśli w 2026 roku sprzedawałeś akcje, ETF-y, obligacje lub inne instrumenty finansowe — musisz złożyć PIT-38. W tym artykule znajdziesz wszystko, co musisz wiedzieć: termin, zmiany w przepisach, sposoby złożenia i konsekwencje spóźnienia.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Termin składania PIT-38 za 2026 rok

**Termin: 30 kwietnia 2027 (środa)**

PIT-38 za rok podatkowy 2026 musisz złożyć najpóźniej do **30 kwietnia 2027**. To termin ustawowy, wynikający z art. 45 ust. 1 ustawy o PIT. Dotyczy wszystkich podatników — niezależnie od tego, czy rozliczasz się przez e-Deklaracje, Twój e-PIT czy papierowo.

### Ważne daty w kalendarzu

| Data | Wydarzenie |
|---|---|
| Do 28 lutego 2027 | Polskie domy maklerskie wysyłają PIT-8C |
| Od 15 lutego 2027 | Twój e-PIT dostępny w e-Urzędzie Skarbowym |
| **30 kwietnia 2027** | **Termin złożenia PIT-38** |
| 30 kwietnia 2027 | Termin zapłaty podatku |

### Czy termin może się przesunąć?

Tak, ale to rzadkość. Termin przesuwa się automatycznie, gdy 30 kwietnia wypada w sobotę lub niedzielę — wtedy przesuwa się na najbliższy dzień roboczy. W 2027 roku 30 kwietnia to środa, więc termin **nie przesuwa się**.

Minister Finansów może też przesunąć termin rozporządzeniem — tak było w 2020 roku (pandemia COVID-19) i w 2022 roku (wdrożenie Polskiego Ładu). Nie ma podstaw, by oczekiwać przesunięcia w 2027 roku, ale warto śledzić komunikaty Ministerstwa Finansów.

## Co się zmieniło w roku podatkowym 2026?

### Stawka podatku od zysków kapitałowych

**Bez zmian: 19%.** Stawka podatku od zysków kapitałowych (tzw. podatek Belki) pozostaje na poziomie 19%. Od lat pojawiają się dyskusje o podwyższeniu stawki lub wprowadzeniu progresji (wyższa stawka dla większych zysków), ale na rok podatkowy 2026 stawka nie uległa zmianie.

### Kwota wolna od podatku od zysków kapitałowych

**Brak kwoty wolnej.** W odróżnieniu od podatku dochodowego od pracy (PIT-36/37), podatek od zysków kapitałowych nie ma kwoty wolnej. Nawet 1 PLN zysku podlega opodatkowaniu 19%.

Temat kwoty wolnej od zysków kapitałowych wraca regularnie w debacie publicznej. Niektóre kraje UE (np. Niemcy — Sparerpauschbetrag 1 000 EUR, Wielka Brytania — Capital Gains Tax allowance) mają takie mechanizmy. W Polsce na rok 2026 kwota wolna **nie została wprowadzona**.

### Kryptowaluty a PIT-38

Przychody z odpłatnego zbycia kryptowalut nadal rozlicza się w PIT-38 (art. 17 ust. 1f ustawy o PIT). Koszty uzyskania przychodu to udokumentowane wydatki na nabycie — faktury, potwierdzenia przelewów. Zasady FIFO stosuje się analogicznie.

W 2026 roku nie wprowadzono istotnych zmian w rozliczeniu kryptowalut. Wciąż nie ma dedykowanego formularza — kryptowaluty wchodzą do tego samego PIT-38 co akcje i ETF-y, ale w osobnym wierszu (Część D formularza).

### Obowiązki informacyjne brokerów

Polskie domy maklerskie nadal mają obowiązek wysłać PIT-8C do **28 lutego** roku następnego. Brokerzy zagraniczni (IBKR, Degiro, Exante) nie mają takiego obowiązku — rozliczenie leży po stronie podatnika.

### Zmiany w formularzach

Ministerstwo Finansów regularnie aktualizuje wzory formularzy. Nowy wariant PIT-38 za 2026 rok zostanie opublikowany na przełomie stycznia i lutego 2027. Zmiany mogą dotyczyć:

- Numeracji pozycji (pól) formularza
- Układu sekcji
- Schematu XSD (format pliku XML)

Jeśli używasz narzędzia do generowania XML (np. TaxPilot), upewnij się, że używa aktualnego schematu. Plik XML z zeszłorocznym wariantem zostanie odrzucony przez bramkę e-Deklaracji.

Więcej o formacie XML: [PIT-38 XML — jak wysłać przez e-Deklaracje](/blog/pit-38-xml-e-deklaracje)

## Twój e-PIT vs własny XML — co wybrać?

### Twój e-PIT

Od kilku lat Ministerstwo Finansów udostępnia usługę **Twój e-PIT** w ramach e-Urzędu Skarbowego (login.gov.pl). System automatycznie przygotowuje wstępną wersję PIT-38 na podstawie danych z PIT-8C otrzymanych od polskich brokerów.

**Zalety:**
- Dane z PIT-8C od polskich brokerów wypełnione automatycznie
- Prosty interfejs — wypełniasz brakujące pola
- Nie musisz generować XML
- Zintegrowany z profilem zaufanym

**Ograniczenia:**
- **Nie uwzględnia transakcji od brokerów zagranicznych** — IBKR, Degiro, Bossa Zagranica, Exante. Musisz ręcznie uzupełnić dane.
- **Nie stosuje FIFO cross-broker** — jeśli masz ten sam instrument u polskiego i zagranicznego brokera
- **Nie generuje PIT/ZG z danych zagranicznych** — dywidendy od brokerów zagranicznych musisz wpisać ręcznie
- **Brak audit trail** — nie masz szczegółowego raportu z obliczeniami

### Własny XML

Generujesz plik XML (np. z TaxPilot) i wysyłasz przez bramkę e-Deklaracji. Twój XML **nadpisuje** wersję przygotowaną w Twój e-PIT.

**Zalety:**
- Pełna kontrola nad danymi
- Uwzględnia wszystkich brokerów (polskich i zagranicznych)
- FIFO cross-broker
- Automatyczny PIT/ZG
- Audit trail

**Ograniczenia:**
- Musisz samodzielnie wygenerować XML (lub użyć narzędzia)
- Potrzebujesz danych autoryzujących do wysłania

### Która opcja dla kogo?

| Sytuacja | Rekomendacja |
|---|---|
| Tylko polski broker z PIT-8C | Twój e-PIT |
| Polski broker + zagraniczny | Własny XML (uwzględnia obie źródła) |
| Tylko brokerzy zagraniczni | Własny XML |
| Złożony portfel (wielu brokerów, dywidendy) | Własny XML z narzędziem (np. TaxPilot) |

Porównanie narzędzi do generowania XML: [Kalkulator podatku giełdowego — porównanie narzędzi](/blog/kalkulator-podatku-gieldowego-porownanie)

## Kary za nieterminowe złożenie PIT-38

Co się stanie, jeśli nie złożysz PIT-38 do 30 kwietnia 2027?

### Czynny żal — zanim urząd się dowie

Jeśli spóźnisz się, ale złożysz deklarację zanim urząd skarbowy sam to wykryje — możesz złożyć **czynny żal** (art. 16 Kodeksu karnego skarbowego). To pismo, w którym informujesz urząd o naruszeniu i prosisz o odstąpienie od kary.

Warunki skutecznego czynnego żalu:
- Złożenie **zanim** urząd wykryje naruszenie (np. zanim dostaniesz wezwanie)
- Jednoczesne złożenie zaległej deklaracji
- Zapłata zaległego podatku z odsetkami

Czynny żal jest skuteczny w zdecydowanej większości przypadków — urzędy go akceptują, jeśli spełnisz warunki.

### Kara za wykroczenie/przestępstwo skarbowe

Jeśli urząd wykryje brak deklaracji bez czynnego żalu, grozi Ci:

- **Wykroczenie skarbowe** (kwota podatku do 5-krotności minimalnego wynagrodzenia) — grzywna od 1/10 do 20-krotności minimalnego wynagrodzenia
- **Przestępstwo skarbowe** (kwota podatku powyżej 5-krotności) — grzywna do 720 stawek dziennych, kara pozbawienia wolności do lat 2 (w praktyce kara więzienia za niezłożenie PIT jest ekstremalnie rzadka)

### Odsetki za zwłokę

Niezależnie od kar, za każdy dzień opóźnienia w zapłacie podatku naliczane są **odsetki za zwłokę**. Stawka odsetek to aktualnie ok. 14,5% rocznie (podwójna stopa referencyjna NBP + 2 pp.). Odsetki nalicza się od dnia następnego po terminie płatności (1 maja 2027) do dnia zapłaty.

Uwaga: odsetki za zwłokę nie podlegają odliczeniu od podatku.

### Praktyczne porady

1. **Nie czekaj do 30 kwietnia.** Złóż PIT-38 wcześniej — unikniesz stresu i ewentualnych problemów technicznych z bramką e-Deklaracji, która w ostatnich dniach bywa przeciążona.
2. **Zacznij zbierać dane w lutym/marcu.** Eksportuj raporty z brokerów, uporządkuj dywidendy, przygotuj obliczenia.
3. **Zapłać podatek w dniu złożenia.** Termin zapłaty jest taki sam jak termin złożenia — 30 kwietnia.

## Korekta PIT-38

Złożyłeś PIT-38, ale znalazłeś błąd? Bez paniki — masz prawo złożyć korektę.

### Kiedy korygować?

- Błąd w obliczeniach (np. pomyłka w kursie NBP)
- Pominięta transakcja
- Błędne dane osobowe
- Brak PIT/ZG (dywidendy zagraniczne)

### Jak złożyć korektę?

1. Przygotuj nowy PIT-38 z poprawnymi danymi.
2. W polu "Cel złożenia" zaznacz **"korekta"** (zamiast "złożenie").
3. Wyślij przez e-Deklaracje (nowy XML) lub Twój e-PIT.

Korekta nadpisuje poprzednią wersję deklaracji. Możesz korygować wielokrotnie — liczy się ostatnia złożona wersja.

### Termin na korektę

Korektę możesz złożyć w ciągu **5 lat** od końca roku, w którym upłynął termin złożenia deklaracji. Dla PIT-38 za 2026 rok: do 31 grudnia 2032.

Wyjątek: nie możesz korygować deklaracji w trakcie kontroli podatkowej lub postępowania podatkowego dotyczącego tego okresu.

### Korekta a odsetki

Jeśli korekta skutkuje wyższym podatkiem — musisz dopłacić różnicę z odsetkami za zwłokę (liczonymi od pierwotnego terminu, tj. 30 kwietnia 2027). Jeśli korekta zmniejsza podatek — masz prawo do zwrotu nadpłaty.

## Odliczanie strat z lat ubiegłych

Jeśli w poprzednich latach (2021-2025) poniosłeś stratę ze sprzedaży papierów wartościowych, możesz ją odliczyć w PIT-38 za 2026 rok.

Zasady:
- Stratę odliczasz w **Części E** formularza PIT-38
- Odliczenie max **50% straty w jednym roku**
- Strata "przepada" po **5 latach** od roku poniesienia
- Odliczasz od dochodu, nie od podatku

Przykład: W 2024 roku poniosłeś stratę 10 000 PLN. W 2026 masz dochód 8 000 PLN. Możesz odliczyć max 5 000 PLN (50% straty). Dochód po odliczeniu: 3 000 PLN. Podatek: 3 000 x 19% = 570 PLN. Pozostałe 5 000 PLN straty możesz odliczyć w kolejnych latach (do 2029).

## Obowiązki informacyjne — kto co wysyła i kiedy

### Polski broker → PIT-8C → Twój e-PIT

Polski dom maklerski (np. mBank, XTB PL, Bossa PL) wysyła PIT-8C do urzędu skarbowego i do Ciebie do 28 lutego. Dane z PIT-8C trafiają automatycznie do Twój e-PIT.

### Broker zagraniczny → nic → Ty rozliczasz sam

Broker zagraniczny (IBKR, Degiro, Bossa Zagranica, Exante) nie wysyła PIT-8C. Nie ma obowiązku informacyjnego wobec polskiego urzędu skarbowego. Cały obowiązek rozliczenia leży po Twojej stronie.

To nie znaczy, że urząd nie wie o Twoich zagranicznych rachunkach. Wymiana informacji podatkowej między krajami (CRS — Common Reporting Standard) oznacza, że zagraniczny broker raportuje informacje o polskich rezydentach podatkowych do swojego lokalnego urzędu, który przekazuje je do polskiego KAS. Urząd **wie**, że masz konto u zagranicznego brokera. Pytanie tylko, kiedy (i czy) to zweryfikuje.

## Jak TaxPilot pomaga dotrzymać terminu

1. **Wgrywasz pliki CSV** z brokerów — polskich i zagranicznych.
2. **TaxPilot oblicza** przychody, koszty, FIFO, dywidendy, kursy NBP.
3. **Generujesz XML** — gotowy do wysłania przez e-Deklaracje.
4. **Wysyłasz** — przez bramkę e-Deklaracji, dostajesz UPO.

Cały proces zajmuje kilkanaście minut zamiast godzin. Nie czekasz na PIT-8C od zagranicznego brokera (bo go nie dostaniesz). Nie szukasz kursów NBP. Nie liczysz FIFO w Excelu.

Jeśli znajdziesz błąd — generujesz nowy XML z oznaczeniem "korekta" i wysyłasz ponownie.

[Rozlicz PIT-38 z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania

### Nie sprzedałem żadnych akcji w 2026 — czy muszę składać PIT-38?

Jeśli nie sprzedałeś instrumentów finansowych i nie otrzymałeś dywidend od brokerów zagranicznych — nie musisz składać PIT-38. Jeśli otrzymałeś dywidendy od zagranicznego brokera — PIT-38 z PIT/ZG jest wymagany.

### Mam PIT-8C od polskiego brokera i transakcje u zagranicznego — co robić?

Łączysz dane z obu źródeł w jednym PIT-38. Dane z PIT-8C (przychody/koszty z polskiego brokera) sumujesz z danymi z zagranicznego brokera. Uwaga na FIFO cross-broker, jeśli masz ten sam instrument u obu.

### Czy mogę złożyć PIT-38 wcześniej niż 30 kwietnia?

Tak. Możesz złożyć PIT-38 w dowolnym momencie po zakończeniu roku podatkowego (od 1 stycznia 2027). Nie musisz czekać na PIT-8C — ale jeśli go dostaniesz, warto zweryfikować dane.

### Podatek wyszedł 0 PLN (strata lub brak zysku) — czy muszę składać PIT-38?

Jeśli miałeś transakcje sprzedaży (przychód ze zbycia) — tak, musisz złożyć PIT-38 nawet jeśli wynik to strata. Strata jest ważna — możesz ją odliczyć w kolejnych latach. Jeśli nie złożysz PIT-38, stracisz prawo do odliczenia.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
