---
title: "Jak rozliczyć Degiro w Polsce — poradnik PIT-38 (2027)"
slug: rozliczenie-degiro
description: "Jak rozliczyć Degiro w PIT-38? Poradnik krok po kroku: eksport raportów, przeliczenie na PLN, FIFO, dywidendy. Degiro nie wysyła PIT-8C — musisz to zrobić sam."
date: 2026-10-15
keywords: [jak rozliczyc degiro w polsce, degiro pit-38, degiro podatek polska, rozliczenie degiro, degiro pit-8c]
schema: Article
---

# Jak rozliczyć Degiro w Polsce — poradnik PIT-38 (2027)

Degiro przyciąga polskich inwestorów niskimi prowizjami i prostym interfejsem. Jest jednak jedno "ale": Degiro to broker holenderski i **nie wysyła PIT-8C**. Nie generuje też gotowego zestawienia podatkowego dostosowanego do polskich przepisów. Całe rozliczenie leży na Tobie.

Nie panikuj — ten poradnik przeprowadzi Cię przez cały proces krok po kroku.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Dlaczego Degiro nie wysyła PIT-8C?

PIT-8C to informacja podatkowa, którą **polski** broker ma obowiązek przesłać do urzędu skarbowego i do klienta. Degiro jest zarejestrowane w Holandii (flatexDEGIRO Bank Dutch Branch) i nie podlega polskim przepisom o PIT-8C.

To nie znaczy, że nie musisz się rozliczać. Jako polski rezydent podatkowy masz obowiązek wykazać dochody ze **wszystkich źródeł** — w tym od zagranicznych brokerów.

Więcej o różnicy między PIT-8C a PIT-38 przeczytasz tutaj: [PIT-8C a PIT-38 — czym się różnią i kiedy który?](/blog/pit-8c-vs-pit-38-roznice)

## Krok 1: Eksport raportów z Degiro

Do rozliczenia potrzebujesz **dwóch raportów** z Degiro. Oba pobierzesz z platformy webowej.

### Raport Transactions (Transakcje)

1. Zaloguj się na [trader.degiro.nl](https://trader.degiro.nl).
2. Przejdź do **Activity** → **Transactions**.
3. Ustaw zakres dat: **01.01.2026 — 31.12.2026**.
4. Kliknij **Export** → **CSV**.

> **Wskazówka:** Na stronie Transactions w Degiro ustaw daty od 01.01 do 31.12, a następnie kliknij przycisk Export w prawym górnym rogu i wybierz format CSV.

Ten raport zawiera:

| Kolumna | Znaczenie |
|---|---|
| Date | Data transakcji |
| Product | Nazwa instrumentu (np. "APPLE INC - AAPL") |
| ISIN | Międzynarodowy numer identyfikacyjny (np. US0378331005) |
| Quantity | Ilość (dodatnia = kupno, ujemna = sprzedaż) |
| Price | Cena za sztukę w walucie oryginału |
| Local value | Wartość w walucie oryginału |
| Value in PLN | Wartość w PLN (**uwaga: kurs Degiro, nie NBP — nie używaj do PIT**) |
| Transaction costs | Prowizja |
| Total | Łączna kwota z prowizją |

**Ważne:** Kolumna "Value in PLN" w raporcie Degiro przelicza po kursie Degiro (kurs rynkowy z momentu transakcji), a nie po kursie średnim NBP. Do PIT-38 **nie możesz** użyć tego kursu — musisz przeliczyć samodzielnie po kursie NBP.

### Raport Account Statement (Wyciąg z konta)

1. Przejdź do **Activity** → **Account Statement**.
2. Ustaw zakres dat: **01.01.2026 — 31.12.2026**.
3. Kliknij **Export** → **CSV**.

> **Wskazówka:** W Account Statement szukaj pozycji z opisem "Dividend" i "Dividend Tax" — para takich wpisów dla tego samego instrumentu i daty to dywidenda brutto i potrącony podatek u źródła.

Ten raport zawiera operacje na koncie, w tym:

- **Dividends** — wypłaty dywidend (kwota netto, po potrąceniu WHT)
- **Dividend Tax** — kwota potrąconego podatku u źródła
- **FX Credit/Debit** — przewalutowania
- **Transaction fees** — dodatkowe opłaty

Account Statement jest kluczowy dla dywidend, bo raport Transactions nie zawiera informacji o dywidendach.

## Krok 2: Identyfikacja transakcji do rozliczenia

Z raportu Transactions wyfiltruj:

1. **Sprzedaże** (Quantity ujemne) — to generuje przychód.
2. **Kupna powiązane ze sprzedażami** (wg FIFO) — to koszt uzyskania przychodu.

Jeśli w 2026 roku tylko kupowałeś akcje i niczego nie sprzedałeś — nie masz przychodu ze zbycia. PIT-38 z tytułu transakcji nie składasz (ale sprawdź dywidendy).

### Przykład — lista transakcji za 2026

| Data | Produkt | Ilość | Cena (EUR) | Prowizja (EUR) |
|---|---|---|---|---|
| 12.02.2026 | VWCE | +10 | 105,20 | 2,00 |
| 18.05.2026 | VWCE | +5 | 110,50 | 2,00 |
| 03.08.2026 | ASML | +3 | 680,00 | 4,50 |
| 15.10.2026 | VWCE | -8 | 118,30 | 2,00 |
| 20.11.2026 | ASML | -3 | 720,00 | 4,50 |

Sprzedaże: VWCE (8 szt. 15.10) i ASML (3 szt. 20.11). To rozliczamy.

## Krok 3: Przeliczenie na PLN po kursach NBP

Dla każdej transakcji potrzebujesz kursu średniego NBP z **dnia roboczego poprzedzającego** dzień transakcji.

### Przeliczenie VWCE

**Sprzedaż 15.10.2026 — 8 szt. po 118,30 EUR**
- Kurs NBP z 14.10.2026: 4,32 PLN/EUR
- Przychód: 8 x 118,30 x 4,32 = **4 088,83 PLN**
- Prowizja sprzedaży: 2,00 x 4,32 = **8,64 PLN**

**Kupno (FIFO) — pierwsze 8 sztuk:**

Wg FIFO bierzesz najpierw kupno z 12.02 (10 szt.), z tego 8 szt.:
- Kurs NBP z 11.02.2026: 4,28 PLN/EUR
- Koszt: 8 x 105,20 x 4,28 = **3 602,05 PLN**
- Prowizja kupna (proporcjonalnie 8/10): 2,00 x 8/10 x 4,28 = **6,85 PLN**

**Dochód z VWCE:** 4 088,83 - 3 602,05 - 8,64 - 6,85 = **471,29 PLN**

### Przeliczenie ASML

**Sprzedaż 20.11.2026 — 3 szt. po 720,00 EUR**
- Kurs NBP z 19.11.2026: 4,35 PLN/EUR
- Przychód: 3 x 720 x 4,35 = **9 396,00 PLN**
- Prowizja sprzedaży: 4,50 x 4,35 = **19,58 PLN**

**Kupno 03.08.2026 — 3 szt. po 680,00 EUR**
- Kurs NBP z 02.08.2026 (piątek, bo 03.08 to poniedziałek): 4,30 PLN/EUR
- Koszt: 3 x 680 x 4,30 = **8 772,00 PLN**
- Prowizja kupna: 4,50 x 4,30 = **19,35 PLN**

**Dochód z ASML:** 9 396,00 - 8 772,00 - 19,58 - 19,35 = **585,07 PLN**

### Podsumowanie transakcji

| Instrument | Przychód (PLN) | Koszt (PLN) | Dochód (PLN) |
|---|---|---|---|
| VWCE | 4 088,83 | 3 617,54 | 471,29 |
| ASML | 9 396,00 | 8 810,93 | 585,07 |
| **Razem** | **13 484,83** | **12 428,47** | **1 056,36** |

Podatek (19%): 1 056,36 x 0,19 = **200,71 PLN**

## Krok 4: Rozliczenie dywidend

Z Account Statement wyciągnij pozycje typu "Dividend" i "Dividend Tax".

### Przykład

Z Account Statement:

| Data | Opis | Kwota (EUR) |
|---|---|---|
| 20.06.2026 | Dividend: VWCE | 12,50 |
| 20.06.2026 | Dividend Tax: VWCE | -1,88 |

Kurs NBP z 19.06.2026: 4,30 PLN/EUR.

- Dywidenda brutto: 12,50 x 4,30 = **53,75 PLN**
- WHT zapłacony: 1,88 x 4,30 = **8,08 PLN**
- Podatek PL (19%): 53,75 x 0,19 = **10,21 PLN**
- Do dopłaty: 10,21 - 8,08 = **2,13 PLN**

Degiro potrąca WHT wg stawki kraju rejestracji funduszu/spółki. Dla irlandzkich ETF-ów (jak VWCE) to zazwyczaj 15%.

### Ważne: Degiro a WHT

Degiro nie zawsze poprawnie stosuje stawki WHT wynikające z umów o unikaniu podwójnego opodatkowania. Sprawdź, czy potrącona kwota odpowiada stawce z umowy bilateralnej. W przypadku ETF-ów zarejestrowanych w Irlandii stawka WHT na dywidendy z USA wynosi 15% (na poziomie funduszu), ale dywidenda wypłacana z funduszu do Ciebie może podlegać jeszcze irlandzkiemu WHT.

## Krok 5: Wypełnienie PIT-38

Mając obliczone kwoty:

### Część C formularza PIT-38

- **Przychód:** 13 484,83 PLN (suma przychodów ze sprzedaży)
- **Koszty uzyskania przychodu:** 12 428,47 PLN (suma kosztów kupna + prowizje)
- **Dochód:** 1 056,36 PLN

### Załącznik PIT/ZG

Dywidendy z zagranicy — w naszym przykładzie z Irlandii:
- Dochód: 53,75 PLN
- Podatek zapłacony za granicą: 8,08 PLN

### Część F — obliczenie podatku

- Podatek od zysków kapitałowych: 1 056,36 x 19% = 200,71 PLN
- Podatek od dywidend: 10,21 PLN, minus WHT 8,08 PLN = 2,13 PLN
- **Razem do zapłaty:** 202,84 PLN

## Krok 6: Prowizje w Degiro — na co uważać

Degiro ma specyficzną strukturę opłat:

1. **Transaction costs** — widoczne w raporcie Transactions. To standardowa prowizja.
2. **Connectivity fee** — opłata za dostęp do giełdy (np. 2,50 EUR rocznie za giełdę USA). To **nie jest** koszt uzyskania przychodu — to opłata za utrzymanie konta.
3. **FX fee** — opłata za przewalutowanie (autoFX). Degiro automatycznie przewalutowuje przy transakcjach w obcej walucie. Opłata wynosi ok. 0,25%. Jest widoczna w Account Statement.

Do kosztów uzyskania przychodu w PIT-38 doliczasz **transaction costs** i **FX fee** związane z konkretnymi transakcjami. Connectivity fee to koszt ogólny, który nie kwalifikuje się jako bezpośredni koszt uzyskania przychodu.

## Jak TaxPilot to upraszcza

Ręczne przeliczanie transakcji z Degiro jest szczególnie uciążliwe, bo:

- Kolumna "Value in PLN" w raporcie używa kursu Degiro, nie NBP — musisz przeliczyć od nowa.
- Dywidendy są w osobnym raporcie (Account Statement).
- Prowizje FX trzeba wyciągać z Account Statement i przypisywać do transakcji.

**TaxPilot** robi to automatycznie:

1. Wgrywasz dwa pliki CSV z Degiro (Transactions + Account Statement).
2. TaxPilot rozpoznaje format, mapuje transakcje, pobiera kursy NBP.
3. Dostajesz gotowe rozliczenie PIT-38 + PIT/ZG w formacie XML.

Bez Excela, bez ręcznego szukania kursów, bez FIFO na kartce.

[Rozlicz Degiro z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania

### Degiro przysłał Annual Report — czy mogę go użyć do PIT?

Degiro wysyła Annual Report, ale jest on przeznaczony do celów informacyjnych, nie do polskiego rozliczenia podatkowego. Nie zawiera przeliczenia na PLN po kursach NBP. Użyj raportów Transactions i Account Statement.

### Mam konto Degiro w EUR — czy moje depozyty/wypłaty to transakcja walutowa?

Nie. Wpłata PLN → EUR na konto Degiro to przewalutowanie, ale nie generuje przychodu podatkowego. Przychód podatkowy powstaje dopiero przy sprzedaży instrumentów finansowych.

### Degiro zamknął moją pozycję z powodu corporate action — co robić?

Traktuj to jak zwykłą sprzedaż. Jeśli otrzymałeś gotówkę (np. z wykupu obligacji czy squeeze-out) — to przychód. Jeśli dostałeś nowe akcje (np. ze splitu) — to tylko zmiana ilości i ceny, bez przychodu.

### Czy mogę łączyć Degiro z innymi brokerami w jednym PIT-38?

Tak, a nawet musisz. Jeśli masz konta u kilku brokerów, sumujesz wyniki ze wszystkich w jednym PIT-38.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
