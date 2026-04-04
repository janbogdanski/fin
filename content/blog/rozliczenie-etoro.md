---
title: "Rozliczenie eToro PIT-38 — jak rozliczyć akcje i dywidendy za 2025 rok?"
slug: rozliczenie-etoro
description: "Rozliczenie eToro PIT-38 2025: jak pobrać Account Statement, przeliczyć USD na PLN po kursach NBP i rozliczyć dywidendy. eToro nie wystawia PIT-8C — zrób to sam."
date: 2026-04-04
keywords: [rozliczenie etoro pit-38 2025, etoro podatek polska, etoro pit-8c, etoro akcje rozliczenie, etoro dywidendy podatek, etoro account statement csv]
schema: Article
---

# Rozliczenie eToro PIT-38 — jak rozliczyć akcje i dywidendy za 2025 rok?

eToro to jeden z najpopularniejszych brokerów CFD i akcji wśród polskich inwestorów. Platforma jest zarejestrowana na Cyprze (eToro (Europe) Ltd) i **nie wystawia PIT-8C**. Nie prześlę Ci gotowego zestawienia do urzędu skarbowego — cały ciężar rozliczenia leży po Twojej stronie.

Po przeczytaniu tego artykułu będziesz wiedział, gdzie pobrać dane z eToro, które transakcje podlegają PIT-38 a które nie, jak przeliczyć kwoty w USD na PLN po kursie NBP i jak rozliczyć dywidendy. Na końcu pokażę, jak TaxPilot importuje Account Statement z eToro i automatyzuje całe obliczenie.

Jedna rzecz, której większość ludzi nie wie: eToro oferuje dwa zupełnie różne rodzaje instrumentów — **real stocks** (rzeczywiste akcje) i **CFD**. Podlegają one różnym przepisom podatkowym. Błędne zakwalifikowanie CFD jako art. 30b PIT (zyski kapitałowe) to jeden z najczęstszych błędów przy rozliczaniu eToro.

## Spis treści

1. [Krok 1 — Pobierz wyciąg z eToro](#krok-1)
2. [Krok 2 — Jakie transakcje podlegają PIT-38?](#krok-2)
3. [Krok 3 — Przeliczenie na PLN (kurs NBP)](#krok-3)
4. [Krok 4 — Dywidendy na eToro](#krok-4)
5. [Krok 5 — Importuj do TaxPilot](#krok-5)
6. [Najczęstsze pytania](#faq)

---

## Krok 1 — Pobierz wyciąg z eToro {#krok-1}

Do rozliczenia potrzebujesz **Account Statement** — to jedyny raport eToro zawierający pełną historię transakcji z cenami otwarcia i zamknięcia pozycji.

### Jak pobrać Account Statement

1. Zaloguj się na [etoro.com](https://www.etoro.com) — wersja webowa, nie aplikacja mobilna.
2. Przejdź do sekcji **Portfolio** → **History**.
3. Kliknij ikonę w prawym górnym rogu (trybik lub „Settings") i wybierz **Account Statement**.
4. Ustaw zakres dat: **01.01.2025 — 31.12.2025**.
5. Kliknij **Create** i poczekaj na wygenerowanie. Plik wysyłany jest na adres e-mail lub pobierany bezpośrednio.
6. Pobierz plik w formacie **XLSX** lub **CSV**.

[Ilustracja: Widok sekcji "History" w panelu eToro — przycisk "Account Statement" w prawym górnym rogu, zakres dat 01.01.2025–31.12.2025, opcja eksportu do CSV]

### Kolumny, które Cię interesują

Account Statement zawiera wiele zakładek. Do PIT-38 potrzebna jest zakładka **Closed Positions** (zamknięte pozycje):

| Kolumna | Znaczenie |
|---|---|
| Date | Data zamknięcia pozycji (to jest data sprzedaży dla celów podatkowych) |
| Action | Typ transakcji (Buy/Sell — dla akcji; Open/Close — dla CFD) |
| Symbol | Ticker instrumentu (np. AAPL, TSLA, AMZN) |
| Amount | Kwota zainwestowana w USD |
| Units | Ilość jednostek (akcji lub kontraktów) |
| Open Rate | Cena otwarcia pozycji w USD |
| Close Rate | Cena zamknięcia pozycji w USD |
| Profit | Zysk/strata na pozycji w USD |

Zwróć uwagę: kolumna **Open Date** (data otwarcia) jest potrzebna do ustalenia kosztu — zasada FIFO (art. 24 ust. 10 ustawy o PIT) wymaga, żebyś sprzedawał jednostki w kolejności nabycia.

**Nie ma w tym pliku gotowego przeliczenia na PLN — musisz wykonać je samodzielnie na podstawie kursów NBP.**

---

## Krok 2 — Jakie transakcje podlegają PIT-38? {#krok-2}

To kluczowy punkt i najczęstsze źródło błędów przy rozliczaniu eToro.

### Real stocks (nielewarowane pozycje na rzeczywistych akcjach)

Jeśli kupowałeś akcje bez dźwigni (oznaczone w eToro jako „Real Stock" — brak znaku „x2", „x5" itp. przy instrumencie), jesteś rzeczywistym właścicielem akcji. Takie transakcje podlegają **art. 30b ustawy o PIT** — zyski kapitałowe, stawka 19%, rozliczane w PIT-38.

### CFD (kontrakty na różnicę)

Jeśli otwierałeś pozycje z dźwignią lub instrumenty oznaczone jako CFD (np. indeksy, surowce, kryptowaluty, forex na eToro) — to **nie są** papiery wartościowe. CFD na eToro to kontrakty pochodne.

> **Ostrzeżenie:** Przychody z CFD **nie podlegają art. 30b ustawy o PIT**. W zależności od Twojego statusu:
> - Jeśli inwestujesz prywatnie (bez działalności gospodarczej) — CFD mogą podlegać **art. 17 ust. 1 pkt 10 ustawy o PIT** (przychody z realizacji praw wynikających z pochodnych instrumentów finansowych), wykazywane również w PIT-38, ale w osobnej pozycji.
> - Jeśli prowadzisz działalność gospodarczą — mogą podlegać **art. 30c** (podatek liniowy).
>
> Klasyfikacja CFD jest nieoczywista i zależy od okoliczności. [DO LEGAL REVIEW: weryfikacja kwalifikacji CFD u prywatnego inwestora bez DG — art. 17 ust. 1 pkt 10 vs. inne kwalifikacje]

**Praktyczna zasada:** sprawdź w Account Statement kolumnę **Type** lub opis pozycji. Real stocks mają oznaczenie „BUY" bez dźwigni. CFD mają oznaczenie dźwigni (x2, x5) lub symbol z sufiksem wskazującym na instrument pochodny.

| Instrument | Typ | Podstawa prawna |
|---|---|---|
| Apple (AAPL) bez dźwigni | Real Stock | art. 30b ustawy o PIT → PIT-38 |
| Tesla (TSLA) x5 | CFD | art. 17 ust. 1 pkt 10 ustawy o PIT — wymaga weryfikacji |
| Gold CFD | CFD | j.w. |
| S&P 500 ETF | CFD (eToro) | j.w. |

**Zanim zaczniesz liczyć, podziel transakcje na dwie listy: real stocks i CFD.**

---

## Krok 3 — Przeliczenie na PLN (kurs NBP) {#krok-3}

eToro prowadzi rachunkowość w USD. Do PIT-38 musisz przeliczyć każdą transakcję na PLN po **średnim kursie NBP z dnia roboczego poprzedzającego datę transakcji** (art. 11a ust. 1 ustawy o PIT).

Kursy NBP znajdziesz na [nbp.pl](https://www.nbp.pl) → Kursy walut → Archiwum kursów (tabela A).

### Zasada weekendów i świąt

- Transakcja w poniedziałek → kurs NBP z piątku poprzedniego tygodnia.
- Transakcja w środę → kurs NBP ze wtorku.
- Jeśli poprzedni dzień roboczy był dniem wolnym (święto) → kurs z ostatniego dnia roboczego przed nim.

### Przykład przeliczenia — real stock

**Scenariusz:** Kupiłeś 20 akcji AAPL w eToro i sprzedałeś część z nich w 2025 roku.

**Kupno — 12.02.2025, 20 szt. AAPL po Open Rate 185,00 USD**
- Poprzedni dzień roboczy: 11.02.2025
- Kurs NBP USD/PLN z 11.02.2025: [DANE POTRZEBNE: kurs NBP dla USD z dnia 11.02.2025 — podaj wartość przed publikacją]
- Koszt: 20 × 185,00 × [kurs] = [wynik] PLN
- Prowizja eToro (spread wbudowany w cenę — brak osobnej prowizji w Account Statement dla real stocks)

**Sprzedaż — 15.09.2025, 20 szt. AAPL po Close Rate 215,00 USD**
- Poprzedni dzień roboczy: 12.09.2025
- Kurs NBP USD/PLN z 12.09.2025: [DANE POTRZEBNE: kurs NBP dla USD z dnia 12.09.2025 — podaj wartość przed publikacją]
- Przychód: 20 × 215,00 × [kurs] = [wynik] PLN

**Dochód = Przychód (PLN) − Koszt (PLN)**

Zysk w USD (kolumna Profit w Account Statement) **nie może być** użyty bezpośrednio do PIT-38 — musisz osobno przeliczyć przychód i koszt każdej transakcji.

### Ważne: spread eToro jako koszt

eToro nie pobiera prowizji per transakcja dla real stocks — zarabia na spreadzie (różnicy między ceną kupna a sprzedaży). Spread jest wbudowany w cenę Open Rate i Close Rate. Nie ma osobnej kolumny „commission" dla akcji rzeczywistych. Kosztem uzyskania przychodu jest więc: `Units × Open Rate × kurs NBP (z daty kupna)`. Żadnych osobnych pozycji prowizyjnych do doliczenia.

**Przy każdej transakcji liczymy: Units × Rate × kurs NBP z poprzedniego dnia roboczego — widoczne działanie krok po kroku.**

---

## Krok 4 — Dywidendy na eToro {#krok-4}

eToro wypłaca dywidendy dla posiadaczy real stocks jako **„dividend adjustment"** — wpis w Account Statement, zakładka **Transactions** (nie Closed Positions).

### Gdzie znaleźć dywidendy

W Account Statement filtruj zakładkę **Transactions** po typie operacji: szukaj pozycji z opisem „Dividend" lub „Dividend Adjustment".

| Data | Opis | Kwota (USD) |
|---|---|---|
| 15.03.2025 | Dividend — AAPL | 5,20 |
| 18.06.2025 | Dividend — MSFT | 3,80 |

### Stawka podatku i WHT

Dywidendy z akcji zagranicznych podlegają **19% podatkowi** w Polsce (art. 30b ust. 1 ustawy o PIT). Dywidendy wykazujesz w PIT-38 z załącznikiem **PIT/ZG** (osobny dla każdego kraju źródła).

Kluczowa różnica względem innych brokerów: **eToro nie stosuje formularza W-8BEN dla akcji US**. Oznacza to, że WHT od dywidend z akcji amerykańskich może wynosić **30%** (stawka domyślna dla nierezydentów USA bez umowy) zamiast 15% (stawka z UPO Polska-USA).

> **Ostrzeżenie:** Sprawdź w Account Statement, jaka kwota WHT została potrącona od każdej dywidendy. Jeśli eToro potrącił 30% — masz przepłaconą zaliczkę, ale odliczyć możesz tylko tyle, ile faktycznie zapłacono. [DO LEGAL REVIEW: weryfikacja aktualnej polityki WHT eToro dla polskich rezydentów — możliwe zmiany od 2024 roku]

### Przykład — rozliczenie dywidendy

**Dywidenda AAPL — 15.03.2025, 5,20 USD brutto**
- Poprzedni dzień roboczy: 14.03.2025
- Kurs NBP USD/PLN z 14.03.2025: [DANE POTRZEBNE: kurs NBP dla USD z dnia 14.03.2025 — podaj wartość przed publikacją]
- Dywidenda brutto (PLN): 5,20 × [kurs] = [wynik] PLN
- WHT potrącone przez eToro (USD): [sprawdź w Account Statement — kolumna „Withholding Tax" lub „Tax"]
- WHT (PLN): [kwota WHT USD] × [kurs] = [wynik] PLN
- Podatek PL należny (19%): [dywidenda brutto PLN] × 0,19 = [wynik] PLN
- Do dopłaty / nadpłata: podatek PL − WHT zapłacone

**Zasada: WHT zapłacony zagranicą odlicza się od podatku polskiego. Nie możesz odliczyć więcej, niż wynosi podatek należny w Polsce.**

---

## Krok 5 — Importuj do TaxPilot {#krok-5}

Ręczne przeliczanie transakcji z eToro jest uciążliwe z kilku powodów:

- Każda pozycja wymaga osobnego kursu NBP (osobna data kupna i sprzedaży).
- Dywidendy są w innej zakładce niż transakcje — łatwo je przeoczyć.
- Podział real stocks / CFD jest ukryty w kolumnach Account Statement i wymaga ręcznego sortowania.
- FIFO (art. 24 ust. 10 ustawy o PIT) przy wielu transakcjach na tym samym instrumencie wymaga chronologicznego zestawienia kupna i sprzedaży.

**TaxPilot** obsługuje import Account Statement z eToro bezpośrednio:

1. Zaloguj się na [taxpilot.pl](https://taxpilot.pl) i utwórz nowe rozliczenie za rok 2025.
2. Wybierz brokera **eToro** i wgraj plik CSV lub XLSX z Account Statement.
3. TaxPilot automatycznie:
   - rozpoznaje kolumny eToro (Date, Symbol, Units, Open Rate, Close Rate, Profit),
   - oddziela real stocks od CFD,
   - pobiera kursy NBP z archiwum NBP dla każdej daty transakcji (uwzględniając weekendy i święta),
   - stosuje FIFO globalnie (jeśli masz też inne brokery — wgraj ich raporty w tym samym rozliczeniu),
   - identyfikuje dywidendy z zakładki Transactions i generuje PIT/ZG dla każdego kraju.
4. Otrzymujesz gotowy PIT-38 w formacie XML do wgrania na e-Deklaracje lub do wydruku.

Bez Excela. Bez ręcznego szukania kursów NBP. Bez ryzyka błędu w FIFO.

[Rozlicz eToro z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania {#faq}

### Czy CFD na eToro podlegają PIT-38?

Przychody z CFD nie podlegają art. 30b ustawy o PIT tak jak akcje. Mogą być kwalifikowane jako przychody z pochodnych instrumentów finansowych (art. 17 ust. 1 pkt 10 ustawy o PIT) i wykazywane w PIT-38, ale w osobnej pozycji niż zyski ze sprzedaży akcji. Kwalifikacja zależy od okoliczności — w razie wątpliwości skonsultuj się z doradcą podatkowym.

### Czy muszę składać PIT-38 jeśli zamknąłem tylko stratne pozycje?

Tak, jeśli w 2025 roku zamknąłeś jakiekolwiek pozycje (nawet ze stratą), powinieneś złożyć PIT-38. Strata ze sprzedaży akcji lub CFD może być rozliczona z dochodem z tego samego źródła w ciągu kolejnych 5 lat (art. 9 ust. 3 ustawy o PIT). Żeby odliczyć stratę w przyszłości, musisz ją najpierw wykazać w deklaracji za rok, w którym ją poniosłeś.

### Czy mogę używać kursu z kolumny „Profit" w Account Statement eToro?

Nie. Kolumna Profit w Account Statement eToro podaje wynik w USD — różnicę między ceną zamknięcia a ceną otwarcia pozycji. Do PIT-38 musisz obliczyć przychód i koszt **osobno**, każdy przeliczony po kursie NBP z poprzedniego dnia roboczego względem daty danej transakcji (art. 11a ust. 1 ustawy o PIT). Użycie kolumny Profit jako podstawy do podatku to błąd, który urząd skarbowy może zakwestionować.

### Czy eToro stosuje FIFO, czy mogę sam wybrać metodę?

Art. 24 ust. 10 ustawy o PIT narzuca metodę FIFO dla papierów wartościowych — sprzedajesz jednostki nabyte najwcześniej. Nie możesz wybrać innej metody (np. LIFO czy metody średniej ceny). eToro nie stosuje żadnej metody podatkowej — to Ty musisz zastosować FIFO do transakcji z Account Statement, uwzględniając chronologię kupna.

---

Artykuł dotyczy roku podatkowego 2025 (PIT-38 składany do 30 kwietnia 2026).
Ostatnia weryfikacja merytoryczna: 2026-04-04.
Przepisy podatkowe mogą ulec zmianie — przed złożeniem deklaracji sprawdź aktualne przepisy.

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego w rozumieniu
ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym. W przypadku wątpliwości skonsultuj
się z licencjonowanym doradcą podatkowym.*

<!-- QUALITY: Q2-DRAFT | placeholdery: 4 (kursy NBP dla dat: 11.02.2025, 12.09.2025, 14.03.2025 — 3 kursy; 1 kwota WHT w przykładzie dywidendowym) | data: 2026-04-04 -->
