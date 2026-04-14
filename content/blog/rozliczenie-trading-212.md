---
title: "Rozliczenie Trading 212 PIT-38 — jak rozliczyć akcje i AutoInvest za 2025 rok?"
slug: rozliczenie-trading-212
description: "Rozliczenie Trading 212 PIT-38 2025: jak pobrać History CSV, przeliczyć EUR/USD na PLN po kursach NBP i rozliczyć AutoInvest. Trading 212 nie wystawia PIT-8C."
date: 2026-04-04
keywords: [rozliczenie trading 212 pit-38 2025, trading 212 podatek polska, trading 212 pit-8c, trading 212 akcje rozliczenie, trading 212 autoinvest podatek, trading 212 csv historia transakcji]
schema: Article
---

# Rozliczenie Trading 212 PIT-38 — jak rozliczyć akcje i AutoInvest za 2025 rok?

Trading 212 to broker zarejestrowany w Wielkiej Brytanii (Trading 212 UK Ltd) i Unii Europejskiej (Trading 212 Ltd, Bułgaria). Platforma **nie wystawia PIT-8C** — nie przekazuje żadnych danych do polskiego urzędu skarbowego. Całe rozliczenie leży po Twojej stronie.

Po przeczytaniu tego artykułu będziesz wiedział: gdzie pobrać History CSV z Trading 212, które transakcje podlegają PIT-38, jak przeliczyć ceny w EUR i USD na PLN po kursie NBP oraz co zrobić z transakcjami z funkcji AutoInvest (Pie). Na końcu opisuję aktualny stan wsparcia TaxPilot dla tego brokera.

Jedna kwestia, która zaskakuje wielu użytkowników: Trading 212 oferuje zarówno akcje i ETF (rzeczywiste papiery wartościowe), jak i kontrakty CFD — **na oddzielnych rachunkach, ale w tej samej aplikacji**. Kwalifikacja podatkowa różni się dla każdego z tych typów. Mylenie ich to najczęstszy błąd przy rozliczaniu Trading 212.

## Spis treści

1. [Krok 1 — Pobierz History CSV z Trading 212](#krok-1)
2. [Krok 2 — Jakie transakcje podlegają PIT-38?](#krok-2)
3. [Krok 3 — AutoInvest (Pie) — jak rozliczać?](#krok-3)
4. [Krok 4 — Przeliczenie na PLN (kurs NBP)](#krok-4)
5. [Krok 5 — Jak rozliczyć bez adaptera TaxPilot?](#krok-5)
6. [Najczęstsze pytania](#faq)

---

## Krok 1 — Pobierz History CSV z Trading 212 {#krok-1}

Do rozliczenia potrzebujesz pełnej historii transakcji z Trading 212. Platforma udostępnia ją jako plik CSV z sekcji **History**.

### Jak pobrać eksport CSV

1. Zaloguj się na [trading212.com](https://www.trading212.com) lub w aplikacji mobilnej.
2. Przejdź do sekcji **History** (ikona zegara lub „Historia" w menu).
3. W prawym górnym rogu wybierz **Export** (ikona pobierania).
4. Ustaw zakres dat: **01.01.2025 — 31.12.2025**.
5. Wybierz format **CSV** i pobierz plik.

[Ilustracja: Widok sekcji "History" w aplikacji Trading 212 — zakres dat 01.01.2025–31.12.2025, przycisk "Export" w prawym górnym rogu, format CSV]

### Struktura pliku CSV

Plik History z Trading 212 zawiera następujące kolumny:

| Kolumna | Znaczenie |
|---|---|
| Date | Data i godzina transakcji (UTC) |
| Action | Typ operacji: `Market buy`, `Market sell`, `Limit buy`, `Limit sell`, itp. |
| Symbol | Ticker instrumentu (np. AAPL, VWCE) |
| No. of shares | Ilość kupionych lub sprzedanych jednostek |
| Price per share | Cena jednej jednostki w walucie notowania |
| Currency conversion fee | Opłata za przewalutowanie (jeśli dotyczy) |
| Total | Łączna wartość transakcji (po prowizji i opłacie przewalutowania) |

**Waluta transakcji zależy od giełdy:** akcje na NYSE/NASDAQ są w USD, akcje na giełdach europejskich mogą być w EUR lub GBP. Kolumna `Price per share` nie podaje wprost, w jakiej walucie — sprawdź instrument i giełdę notowania.

Trading 212 nie pobiera prowizji per transakcja dla rachunku Invest (akcje i ETF). Kolumna `Currency conversion fee` pojawia się wtedy, gdy instrument jest notowany w innej walucie niż waluta Twojego rachunku.

**Kosztem uzyskania przychodu dla każdej transakcji jest: `No. of shares × Price per share` (w walucie notowania) plus `Currency conversion fee` — przeliczone na PLN po kursie NBP.**

---

## Krok 2 — Jakie transakcje podlegają PIT-38? {#krok-2}

Trading 212 udostępnia dwa oddzielne typy rachunków: **Invest** (akcje i ETF) oraz **CFD**. Eksport CSV jest osobny dla każdego rachunku.

### Rachunek Invest — akcje i ETF

Transakcje z rachunku Invest to zakupy i sprzedaże rzeczywistych papierów wartościowych. Podlegają **art. 30b ustawy o PIT** — zyski kapitałowe, stawka 19%, rozliczane w PIT-38, sekcja C (przychody ze sprzedaży papierów wartościowych).

### Rachunek CFD

Transakcje z rachunku CFD to kontrakty na różnicę. **Nie są** papierami wartościowymi w rozumieniu ustawy o obrocie instrumentami finansowymi.

> **Ostrzeżenie:** Przychody z CFD na Trading 212 nie podlegają art. 30b ustawy o PIT w taki sam sposób jak akcje. W przypadku prywatnego inwestora bez działalności gospodarczej mogą być kwalifikowane jako przychody z realizacji praw z pochodnych instrumentów finansowych (art. 17 ust. 1 pkt 10 ustawy o PIT). [DO LEGAL REVIEW: weryfikacja kwalifikacji CFD z rachunku Trading 212 CFD u prywatnego inwestora — art. 17 ust. 1 pkt 10 vs. art. 30b; potwierdzenie, że wykazuje się w PIT-38]

| Typ rachunku | Instrument | Podstawa prawna |
|---|---|---|
| Invest | Akcje (np. AAPL) | art. 30b ustawy o PIT → PIT-38 sekcja C |
| Invest | ETF (np. VWCE) | art. 30b ustawy o PIT → PIT-38 sekcja C |
| CFD | Kontrakty na indeksy, waluty, surowce | art. 17 ust. 1 pkt 10 ustawy o PIT — wymaga weryfikacji |

**Przed rozpoczęciem rozliczenia zidentyfikuj, z którego rachunku pochodzi każda transakcja i pobierz osobne pliki CSV dla każdego rachunku.**

---

## Krok 3 — AutoInvest (Pie) — jak rozliczać? {#krok-3}

Trading 212 oferuje funkcję **AutoInvest** (zwana też "Pie") — automatyczne inwestowanie według zdefiniowanego portfela. Mechanizm automatycznie kupuje i rebalansuje wybrane instrumenty według zadanych proporcji.

### Co AutoInvest oznacza dla podatku

AutoInvest to tylko interfejs automatyzujący transakcje — **pod spodem każde kupno i sprzedaż to osobna transakcja na rachunku Invest**. W pliku CSV każda z tych transakcji pojawia się jako oddzielny wiersz z typem `Market buy` lub `Market sell`.

Nie ma osobnej kategorii podatkowej dla transakcji z AutoInvest. Każda transakcja traktowana jest identycznie jak ręcznie złożone zlecenie.

**Skutek praktyczny:** jeśli Twoje Pie dokonało w 2025 roku 60 zakupów różnych akcji i 15 sprzedaży przy rebalansowaniu — to 75 oddzielnych transakcji do wykazania w PIT-38.

### Rebalansowanie a przychód podatkowy

Rebalansowanie Pie polega na sprzedaży instrumentów z nadwagą i kupnie instrumentów z niedowagą. **Każda sprzedaż przy rebalansowaniu generuje przychód podatkowy** — nawet jeśli zysk jest niewielki lub całość jest reinwestowana w ramach tego samego Pie.

Przykład: AutoInvest sprzedaje 2 sztuki MSFT w ramach rebalansowania 10.06.2025. To zdarzenie podatkowe. Fakt, że środki trafiają z powrotem do Pie, nie zmienia kwalifikacji.

**Nie możesz agregować transakcji AutoInvest — każda sprzedaż musi być wykazana osobno w PIT-38.**

---

## Krok 4 — Przeliczenie na PLN (kurs NBP) {#krok-4}

Do PIT-38 musisz przeliczyć każdą transakcję z waluty obcej na PLN po **średnim kursie NBP z dnia roboczego poprzedzającego datę transakcji** (art. 11a ust. 1 ustawy o PIT).

Kursy NBP dostępne są na [nbp.pl](https://www.nbp.pl) → Kursy walut → Archiwum kursów (tabela A).

### Zasada weekendów i świąt

- Transakcja w poniedziałek → kurs z piątku.
- Transakcja w środę → kurs ze wtorku.
- Jeśli poprzedni dzień był dniem wolnym od pracy → kurs z ostatniego dnia roboczego przed nim.

### Przykład — akcja notowana w USD

**Kupno: 05.03.2025, 15 szt. AAPL, Price per share: 172,50 USD**
- Poprzedni dzień roboczy: 04.03.2025
- Kurs NBP USD/PLN z 04.03.2025: **3,9543**
- Koszt: 15 × 172,50 × 3,9543 = **10.247,80 PLN**
- Currency conversion fee: sprawdź kolumnę „Currency conversion fee" w CSV — Trading 212 pobiera zwykle ok. 0,15% wartości transakcji (np. 3,88 USD × 3,9543 = **15,34 PLN**); jeśli brak tej kolumny w Twoim pliku, opłata mogła być wliczona w spread
- Łączny koszt (z fee): ok. **10.263,14 PLN**

**Sprzedaż: 18.11.2025, 15 szt. AAPL, Price per share: 228,00 USD**
- Poprzedni dzień roboczy: 17.11.2025
- Kurs NBP USD/PLN z 17.11.2025: **3,6400**
- Przychód: 15 × 228,00 × 3,6400 = **12.441,60 PLN**

**Dochód = 12.441,60 − 10.263,14 = 2.178,46 PLN** (bez fee: 12.441,60 − 10.247,80 = 2.193,80 PLN)

### Przykład — ETF notowany w EUR

**Kupno: 12.02.2025, 5 szt. VWCE, Price per share: 118,40 EUR**
- Poprzedni dzień roboczy: 11.02.2025
- Kurs NBP EUR/PLN z 11.02.2025: **4,1783**
- Koszt: 5 × 118,40 × 4,1783 = **2.475,59 PLN**

Trading 212 często rozlicza transakcje na instrumentach europejskich w EUR. Sprawdź, czy Twój rachunek jest prowadzony w EUR czy GBP — jeśli instrument jest w GBP, używasz kursu NBP GBP/PLN.

**Dla każdej waluty (USD, EUR, GBP) pobierasz osobny kurs NBP z odpowiedniej tabeli A na dzień poprzedzający transakcję.**

---

## Krok 5 — Jak rozliczyć bez adaptera TaxPilot? {#krok-5}

**TaxPilot nie obsługuje aktualnie importu pliku CSV z Trading 212.** Nie ma adaptera dla tego brokera — nie wgraj pliku CSV z Trading 212 do TaxPilot, bo nie zostanie poprawnie przetworzony.

Wsparcie dla importu pliku CSV Trading 212 nie jest aktualnie planowane — aktualny status możesz sprawdzić na [taxpilot.pl](https://taxpilot.pl).

### Opcje, które masz teraz

**Opcja 1: Ręczne wprowadzenie transakcji do TaxPilot**

TaxPilot pozwala na ręczne dodawanie transakcji. Dla każdej sprzedaży z pliku CSV Trading 212 wprowadzasz:
- datę transakcji,
- ticker instrumentu,
- ilość,
- przychód w PLN (obliczony ręcznie z kursu NBP),
- koszt w PLN (obliczony ręcznie z kursu NBP przy kupnie, zgodnie z FIFO).

To rozwiązanie jest pracochłonne przy dużej liczbie transakcji — szczególnie przy AutoInvest.

**Opcja 2: Konwersja CSV przez zewnętrzne narzędzie**

Niektóre narzędzia trzecich stron (np. aplikacje do rozliczeń podatkowych z obsługą Trading 212) mogą przekonwertować plik CSV do formatu zgodnego z TaxPilot lub bezpośrednio do PIT-38 XML. Przed użyciem sprawdź, czy narzędzie używa poprawnych kursów NBP i stosuje [FIFO zgodnie z art. 24 ust. 10 ustawy o PIT](/blog/metoda-fifo-pit-38).

**Opcja 3: Arkusz kalkulacyjny**

Pobierz History CSV z Trading 212, posortuj transakcje chronologicznie, pobierz kursy NBP dla każdej daty z archiwum NBP i oblicz przychody i koszty w Excelu lub Google Sheets. To metoda pracochłonna, ale dająca pełną kontrolę nad danymi.

### Kiedy sprawdź aktualizacje TaxPilot

Jeśli masz dużo transakcji Trading 212 (szczególnie z AutoInvest), sprawdź stronę [taxpilot.pl](https://taxpilot.pl) przed rozliczeniem — adapter dla Trading 212 może być już dostępny w kolejnej wersji platformy.

**Niezależnie od wybranej metody: każda sprzedaż z rachunku Invest Trading 212 musi trafić do PIT-38. Nie pomijaj transakcji z AutoInvest.**

---

## Najczęstsze pytania {#faq}

### Czy Trading 212 przesyła informacje do polskiego urzędu skarbowego?

Nie. Trading 212 to broker UK/EU — nie jest polskim płatnikiem podatkowym i nie wystawia PIT-8C. Urząd skarbowy nie otrzymuje od Trading 212 żadnych danych o Twoich transakcjach. Masz obowiązek samodzielnie wykazać zyski w PIT-38 do 30 kwietnia 2026 (art. 45 ust. 1 ustawy o PIT).

### Jak rozliczyć dywidendy z ETF kupionych na Trading 212?

Trading 212 przekazuje dywidendy z akcji i dystrybuujących ETF jako wpływ na rachunek — znajdziesz je w History jako operacje typu `Dividend`. [Dywidendy podlegają 19% podatkowi](/blog/rozliczenie-dywidend-zagranicznych) (art. 30b ust. 1 ustawy o PIT) i wykazujesz je w PIT-38 z załącznikiem PIT/ZG osobno dla każdego kraju źródła. Dywidend nie łączysz z zyskami ze sprzedaży — to oddzielna pozycja. Jeśli kupujesz akumulujące ETF (np. [VWCE](/blog/etf-irlandzkie-vwce-wht)), platforma nie wypłaci Ci dywidendy — fundusz reinwestuje je automatycznie i nie ma przychodu do wykazania przy samym posiadaniu jednostek.

### Czy muszę składać PIT-38 jeśli poniosłem stratę na Trading 212?

Tak. Jeśli w 2025 roku sprzedałeś jakiekolwiek papiery wartościowe (nawet ze stratą), powinieneś złożyć PIT-38. Stratę ze sprzedaży papierów wartościowych możesz rozliczać z dochodem z tego samego źródła w ciągu kolejnych 5 lat (art. 9 ust. 3 ustawy o PIT). Żeby odliczyć stratę w przyszłości, musisz ją wykazać w deklaracji za rok, w którym ją poniosłeś.

### Kolumna "Total" w pliku CSV — czy mogę jej użyć bezpośrednio do PIT-38?

Nie. Kolumna `Total` podaje wartość transakcji w walucie rachunku Trading 212 (zazwyczaj EUR lub GBP). Do PIT-38 musisz przeliczyć **przychód** i **koszt** osobno, każdy po kursie NBP z poprzedniego dnia roboczego względem odpowiedniej transakcji (art. 11a ust. 1 ustawy o PIT). Użycie kolumny `Total` bezpośrednio pominęłoby zasadę NBP i dałoby błędny wynik.

---

Artykuł dotyczy roku podatkowego 2025 (PIT-38 składany do 30 kwietnia 2026).
Ostatnia weryfikacja merytoryczna: 2026-04-04.
Przepisy podatkowe mogą ulec zmianie — przed złożeniem deklaracji sprawdź aktualne przepisy.

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego w rozumieniu
ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym. W przypadku wątpliwości skonsultuj
się z licencjonowanym doradcą podatkowym.*

<!-- QUALITY: REVIEWED | wszystkie kursy NBP uzupełnione (USD 04.03.2025: 3.9543, USD 17.11.2025: 3.6400, EUR 11.02.2025: 4.1783), currency conversion fee opisane jako ok. 0.15% z przykładem; adapter T212 = brak w v1 (bez DO WERYFIKACJI) | DO LEGAL REVIEW: CFD T212 kwalifikacja art.17 ust.1 pkt 10 vs art.30b (otwarte) | data: 2026-04-14 -->
