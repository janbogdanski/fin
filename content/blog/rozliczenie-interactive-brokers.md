---
title: "Jak rozliczyć Interactive Brokers PIT-38 krok po kroku (2027)"
slug: rozliczenie-interactive-brokers
description: "Przewodnik krok po kroku: jak wyeksportować Activity Statement z Interactive Brokers i rozliczyć PIT-38. FIFO, kursy NBP, dywidendy, prowizje."
date: 2026-10-08
keywords: [pit 38 interactive brokers, rozliczenie interactive brokers, ibkr pit-38, interactive brokers podatek polska, activity statement interactive brokers]
schema: Article
---

# Jak rozliczyć Interactive Brokers PIT-38 krok po kroku (2027)

Interactive Brokers (IBKR) to najpopularniejszy broker zagraniczny wśród polskich inwestorów. Niskie prowizje, dostęp do giełd na całym świecie, solidna platforma. Jest tylko jeden problem: **IBKR nie wysyła PIT-8C**. Rozliczenie podatkowe leży po Twojej stronie.

Dobra wiadomość: IBKR udostępnia szczegółowy raport (Activity Statement), który zawiera wszystkie potrzebne dane. W tym artykule pokażę Ci dokładnie, jak go wyeksportować i jak na jego podstawie wypełnić PIT-38.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Krok 1: Eksport Activity Statement z IBKR

### Gdzie znaleźć raport?

1. Zaloguj się do **Client Portal** (portal.interactivebrokers.com).
2. Przejdź do **Performance & Reports** → **Statements**.
3. Wybierz **Activity Statement**.
4. Ustaw okres: **Annual** (roczny) za rok 2026.
5. Format: **CSV** (łatwiejszy do przetworzenia) lub **PDF** (do wglądu).

> **Wskazówka:** W Client Portal przejdź do Performance & Reports, wybierz Statements, kliknij Activity Statement i ustaw typ okresu na Annual.

### Ustawienia raportu

Upewnij się, że raport zawiera:

- **Trades** — wszystkie transakcje kupna i sprzedaży
- **Dividends** — wypłacone dywidendy
- **Withholding Tax** — podatek u źródła potrącony od dywidend
- **Commissions** — prowizje (zwykle ujęte w sekcji Trades)
- **Corporate Actions** — splity, spin-offy, konwersje

Domyślne ustawienia Activity Statement zawierają te wszystkie sekcje. Jeśli korzystasz z raportu Custom, upewnij się, że je zaznaczyłeś.

### Tip: Flex Query

Zaawansowani użytkownicy mogą użyć **Flex Queries** (Reports → Flex Queries), które pozwalają na bardziej szczegółową konfigurację raportu. TaxPilot wczytuje Activity Statement w formacie CSV; pliki Flex Query w standardowym formacie CSV mogą być wczytane, ale bez gwarancji kompletności danych.

## Krok 2: Zrozumienie sekcji raportu

Activity Statement z IBKR ma kilkanaście sekcji. Do rozliczenia PIT-38 potrzebujesz czterech.

### Sekcja: Trades

Najważniejsza sekcja. Zawiera wszystkie zrealizowane transakcje:

| Pole | Znaczenie |
|---|---|
| Symbol | Ticker instrumentu (np. AAPL, VWCE) |
| Date/Time | Data i godzina transakcji |
| Quantity | Ilość (dodatnia = kupno, ujemna = sprzedaż) |
| T. Price | Cena transakcji |
| Proceeds | Wartość transakcji (przychód ze sprzedaży) |
| Comm/Fee | Prowizja |
| Basis | Koszt nabycia (wg IBKR — **uwaga: IBKR liczy FIFO po swojemu**) |
| Realized P/L | Zysk/strata wg IBKR |

**Ważna uwaga:** Kolumna "Realized P/L" w raporcie IBKR to zysk/strata w walucie transakcji (np. USD). IBKR **nie przelicza na PLN** i **nie stosuje kursów NBP**. Dlatego nie możesz po prostu przepisać tych kwot do PIT-38 — musisz przeliczyć każdą transakcję po kursie NBP z odpowiedniego dnia.

> **Wskazówka:** W pobranym Activity Statement znajdź sekcję Trades — zawiera kolumny Symbol, Date/Time, Quantity, T. Price, Proceeds i Comm/Fee, które są podstawą do przeliczenia na PLN.

### Sekcja: Dividends

Lista wszystkich wypłaconych dywidend:

| Pole | Znaczenie |
|---|---|
| Symbol | Ticker spółki |
| Date | Data wypłaty dywidendy |
| Description | Opis (np. "AAPL(US0378331005) Cash Dividend USD 0.24 per Share") |
| Amount | Kwota brutto dywidendy |

### Sekcja: Withholding Tax

Podatek u źródła potrącony od dywidend:

| Pole | Znaczenie |
|---|---|
| Symbol | Ticker spółki |
| Date | Data potrącenia |
| Amount | Kwota potrąconego podatku (wartość ujemna) |

Żeby obliczyć dywidendę netto i podatek do zapłaty w Polsce, musisz połączyć dane z sekcji Dividends i Withholding Tax dla tego samego symbolu i daty.

### Sekcja: Corporate Actions

Splity akcji (np. Apple 4:1), spin-offy i inne zdarzenia korporacyjne. Nie generują przychodu podatkowego same w sobie, ale zmieniają cenę nabycia i ilość posiadanych akcji — co wpływa na FIFO.

## Krok 3: Przeliczenie na PLN (kursy NBP)

Każdą transakcję w walucie obcej musisz przeliczyć na PLN po **kursie średnim NBP z dnia roboczego poprzedzającego**:

- **Sprzedaż:** kurs z dnia poprzedzającego dzień sprzedaży → przeliczasz przychód
- **Kupno:** kurs z dnia poprzedzającego dzień kupna → przeliczasz koszt
- **Dywidenda:** kurs z dnia poprzedzającego dzień wypłaty → przeliczasz dywidendę i WHT

### Przykład przeliczenia transakcji

Sprzedaż 5 akcji MSFT 15 września 2026 po 410 USD. Prowizja: 1 USD.

- Kurs NBP z 14 września 2026: 4,08 PLN/USD
- Przychód: 5 x 410 x 4,08 = **8 364 PLN**
- Prowizja sprzedaży: 1 x 4,08 = **4,08 PLN** (koszt uzyskania przychodu)

Kupno tych 5 akcji miało miejsce 3 marca 2026 po 380 USD. Prowizja: 1 USD.

- Kurs NBP z 2 marca 2026: 4,02 PLN/USD
- Koszt kupna: 5 x 380 x 4,02 = **7 638 PLN**
- Prowizja kupna: 1 x 4,02 = **4,02 PLN**

Dochód: 8 364 - 7 638 - 4,08 - 4,02 = **717,90 PLN**

Przy 100+ transakcjach rocznie — widzisz, dlaczego ręczne przeliczanie to mordęga.

## Krok 4: Zastosowanie FIFO

IBKR w Activity Statement stosuje [FIFO](/blog/metoda-fifo-pit-38) do obliczania Realized P/L. Ale robi to **w walucie transakcji** (np. USD). Dla celów polskiego PIT-38 musisz zastosować FIFO z przeliczeniem na PLN.

Kluczowa różnica: koszt nabycia w PLN zależy od kursu NBP z dnia kupna. Dwie identyczne transakcje kupna w USD mogą mieć różny koszt w PLN, bo kurs się zmienił.

### Przykład FIFO z wieloma kupnami

| Data | Operacja | Ilość | Cena (USD) | Kurs NBP | Koszt/Przychód (PLN) |
|---|---|---|---|---|---|
| 10.01.2026 | Kupno AAPL | 10 | 175 | 4,00 | 7 000 |
| 15.04.2026 | Kupno AAPL | 10 | 190 | 4,10 | 7 790 |
| 20.09.2026 | Kupno AAPL | 5 | 200 | 4,05 | 4 050 |
| 01.12.2026 | Sprzedaż AAPL | 18 | 220 | 4,15 | 16 434 |

Sprzedaż 18 sztuk wg FIFO:

1. 10 szt. z kupna 10.01 → koszt 7 000 PLN
2. 8 szt. z kupna 15.04 → koszt 8/10 x 7 790 = 6 232 PLN

Łączny koszt: 7 000 + 6 232 = **13 232 PLN**
Przychód: **16 434 PLN**
Dochód: **3 202 PLN**

Pozostaje 2 szt. z kupna 15.04 i 5 szt. z kupna 20.09 — przechodzą na następny rok.

## Krok 5: Rozliczenie dywidend

[Dywidendy z IBKR](/blog/rozliczenie-dywidend-zagranicznych) rozliczasz na **załączniku PIT/ZG**:

1. Z sekcji **Dividends** bierzesz kwotę brutto dywidendy.
2. Z sekcji **Withholding Tax** bierzesz kwotę potrąconego podatku.
3. Przeliczasz obie kwoty na PLN po kursie NBP z dnia poprzedzającego wypłatę.
4. Grupujesz wg krajów (USA, Irlandia, Niemcy itd.).

### Przykład

| Spółka | Kraj | Dywidenda brutto (USD) | WHT (USD) | Data | Kurs NBP | Dywidenda (PLN) | WHT (PLN) |
|---|---|---|---|---|---|---|---|
| AAPL | USA | 48,00 | 7,20 | 15.05.2026 | 4,10 | 196,80 | 29,52 |
| MSFT | USA | 37,20 | 5,58 | 12.06.2026 | 4,08 | 151,78 | 22,77 |
| VWCE | Irlandia | 15,00 | 2,25 | 20.09.2026 | 4,12 | 61,80 | 9,27 |

W PIT/ZG grupujesz:

- **USA:** dochód 348,58 PLN, WHT zapłacony 52,29 PLN
- **Irlandia:** dochód 61,80 PLN, WHT zapłacony 9,27 PLN

Podatek w PL (19%):
- USA: 348,58 x 19% = 66,23 PLN. Do zapłaty: 66,23 - 52,29 = **13,94 PLN**
- Irlandia: 61,80 x 19% = 11,74 PLN. Do zapłaty: 11,74 - 9,27 = **2,47 PLN**

## Krok 6: Wypełnienie PIT-38

Mając przeliczone dane, wypełniasz PIT-38:

1. **Część C** — wpisujesz łączny przychód i koszt ze sprzedaży papierów wartościowych (z kroku 4).
2. **Część E** — odliczasz straty z lat poprzednich (jeśli masz).
3. **Część F** — obliczasz podatek 19%, odliczasz WHT z PIT/ZG.
4. **PIT/ZG** — dołączasz z rozbiciem dywidend na kraje.

## Jak TaxPilot to upraszcza

Zamiast ręcznie przetwarzać Activity Statement, możesz:

1. **Wyeksportować** Activity Statement z IBKR (CSV).
2. **Wgrać** do TaxPilot.
3. **Otrzymać** gotowe rozliczenie: transakcje przeliczone na PLN, FIFO zastosowane, dywidendy pogrupowane, PIT-38 + PIT/ZG w formacie XML.

TaxPilot rozpoznaje format IBKR i automatycznie mapuje sekcje Trades, Dividends i Withholding Tax. Nie musisz ręcznie szukać kursów NBP ani liczyć FIFO w Excelu.

[Rozlicz IBKR z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania

### Czy muszę rozliczać IBKR, jeśli nie sprzedałem żadnych akcji?

Jeśli nie sprzedałeś — nie masz przychodu ze zbycia i nie musisz składać PIT-38 z tego tytułu. Ale jeśli otrzymałeś dywidendy — musisz je rozliczyć (PIT-38 + PIT/ZG).

### Mam konto w IBKR w EUR, nie USD. Czy coś się zmienia?

Zasady są identyczne. Zamiast kursu USD bierzesz kurs EUR z tabeli NBP. Przeliczenie działa tak samo.

### IBKR stosuje FIFO w raporcie — mogę przepisać Realized P/L?

Nie. IBKR liczy P/L w walucie transakcji. Ty musisz przeliczyć każdą transakcję na PLN po odpowiednim kursie NBP, a dopiero potem zastosować FIFO. Wynik w PLN będzie inny niż prosta konwersja Realized P/L.

### Co z odsetkami (Interest) z IBKR?

Odsetki od gotówki na koncie IBKR to osobny przychód. Rozliczasz je w PIT-38 jako przychody z kapitałów pieniężnych.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
