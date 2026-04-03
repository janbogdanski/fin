---
title: "Metoda FIFO w PIT-38 — jak działa i dlaczego jest ważna"
slug: metoda-fifo-pit-38
description: "Czym jest metoda FIFO i dlaczego jest jedyną dozwoloną metodą rozliczania kosztów w PIT-38? Przykłady z trzema kupnami i jedną sprzedażą, cross-broker FIFO, częściowe sprzedaże i najczęstsze błędy."
date: 2026-11-19
keywords: [pit-38 fifo metoda, fifo akcje, metoda fifo pit-38, fifo rozliczenie akcji, fifo koszt uzyskania przychodu, fifo inwestycje]
schema: Article
---

# Metoda FIFO w PIT-38 — jak działa i dlaczego jest ważna

Kupujesz akcje tej samej spółki w różnych momentach, po różnych cenach. Potem sprzedajesz część. Które akcje "odchodzą" pierwsze — te najdroższe, najtańsze, a może ostatnio kupione? To nie jest kwestia wyboru. Polski ustawodawca zdecydował za Ciebie: **obowiązuje FIFO**.

W tym artykule wyjaśniam, czym jest metoda FIFO, dlaczego jest obowiązkowa, i pokazuję konkretne przykłady — w tym sytuację z wieloma brokerami i częściową sprzedażą.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Spis treści

1. [Co to jest FIFO?](#co-to-jest-fifo)
2. [Dlaczego FIFO jest obowiązkowe?](#dlaczego-obowiazkowe)
3. [Przykład: 3 kupna + 1 sprzedaż](#przyklad-3-kupna)
4. [Częściowa sprzedaż — jak liczyć?](#czesciowa-sprzedaz)
5. [Cross-broker FIFO](#cross-broker)
6. [FIFO a różne waluty](#fifo-waluty)
7. [FIFO a ułamkowe akcje](#fifo-ulamkowe)
8. [Najczęstsze błędy](#najczestsze-bledy)
9. [Jak TaxPilot liczy FIFO](#taxpilot)

---

## Co to jest FIFO? {#co-to-jest-fifo}

FIFO (First In, First Out) to metoda wyceny, w której **najstarsze nabyte jednostki są sprzedawane jako pierwsze**. Dosłownie: "pierwsze weszło, pierwsze wyszło".

W kontekście PIT-38 FIFO odpowiada na pytanie: **jaki jest koszt uzyskania przychodu** przy sprzedaży akcji, które kupowałeś w kilku transzach?

### Prosty przykład na start

Kupiłeś akcje XYZ:
- Styczeń: 10 szt. po 100 zł = 1 000 zł
- Marzec: 10 szt. po 120 zł = 1 200 zł

W czerwcu sprzedajesz 10 akcji po 150 zł = 1 500 zł przychodu.

Metodą FIFO sprzedajesz **te ze stycznia** (najstarsze):
- Koszt: 10 × 100 = **1 000 zł**
- Dochód: 1 500 − 1 000 = **500 zł**
- Podatek 19%: **95 zł**

Gdybyś mógł wybrać LIFO (Last In, First Out), sprzedałbyś te z marca:
- Koszt: 10 × 120 = 1 200 zł
- Dochód: 1 500 − 1 200 = 300 zł
- Podatek: 57 zł

Różnica: 38 zł. Przy większych kwotach i większej zmienności cen ta różnica potrafi być znacząca. Ale wyboru nie masz — FIFO jest obowiązkowe.

## Dlaczego FIFO jest obowiązkowe? {#dlaczego-obowiazkowe}

Podstawa prawna: **art. 24 ust. 10 ustawy o podatku dochodowym od osób fizycznych** (ustawa o PIT).

Przepis mówi wprost:

> *Jeżeli podatnik dokonuje odpłatnego zbycia papierów wartościowych nabytych po różnych cenach i nie jest możliwe określenie ceny nabycia zbywanych papierów wartościowych, przy ustalaniu dochodu z takiego zbycia stosuje się zasadę, że każdorazowo zbyciu podlegają kolejno papiery wartościowe nabyte najwcześniej (FIFO).*

### Co to oznacza w praktyce?

- **Nie możesz wybrać** metody LIFO, średniej ważonej ani żadnej innej.
- FIFO stosuje się do **każdego instrumentu osobno** — akcje Apple rozliczasz FIFO osobno od akcji Tesla.
- FIFO działa **chronologicznie** — liczy się data nabycia (trade date), nie data rozliczenia (settlement date).
- FIFO stosuje się **globalnie** — nie per broker, nie per rachunek.

Ten ostatni punkt jest kluczowy i źródłem wielu błędów. Ale do tego wrócimy.

## Przykład: 3 kupna + 1 sprzedaż {#przyklad-3-kupna}

Zobaczmy FIFO w akcji na bardziej realistycznym przykładzie. Inwestujesz w akcje Apple (AAPL) przez Interactive Brokers.

### Transakcje kupna

| Data | Operacja | Ilość | Cena (USD) | Kurs NBP (PLN/USD) | Koszt (PLN) |
|------|----------|-------|------------|-------------------|------------|
| 10.02.2026 | Kupno | 5 | 180 | 4,02 | 3 618,00 |
| 15.05.2026 | Kupno | 3 | 195 | 4,08 | 2 386,80 |
| 20.08.2026 | Kupno | 7 | 170 | 4,05 | 4 819,50 |

Łącznie masz 15 akcji Apple. Łączny koszt nabycia: 10 824,30 PLN.

### Sprzedaż

| Data | Operacja | Ilość | Cena (USD) | Kurs NBP (PLN/USD) | Przychód (PLN) |
|------|----------|-------|------------|-------------------|---------------|
| 10.11.2026 | Sprzedaż | 8 | 200 | 4,10 | 6 560,00 |

### Obliczenie FIFO

Sprzedajesz 8 akcji. Metodą FIFO „zużywasz" kolejno:

**Krok 1:** Cała paczka z 10.02 — 5 akcji po 180 USD × kurs 4,02 = **3 618,00 PLN**

**Krok 2:** Cała paczka z 15.05 — 3 akcje po 195 USD × kurs 4,08 = **2 386,80 PLN**

**Krok 3:** Brakuje jeszcze 0 akcji — kupna z 20.08 nie ruszamy.

5 + 3 = 8 ✓

### Wynik

- **Przychód:** 8 × 200 × 4,10 = **6 560,00 PLN**
- **Koszt uzyskania przychodu:** 3 618,00 + 2 386,80 = **6 004,80 PLN**
- **Dochód:** 6 560,00 − 6 004,80 = **555,20 PLN**
- **Podatek 19%:** 105,49 → **105 PLN** (zaokrąglenie do pełnych złotych)

### Co zostaje na stanie?

Po tej transakcji masz jeszcze 7 akcji z 20.08.2026 (po 170 USD, kurs 4,05). Jeśli je sprzedasz w przyszłości, koszt uzyskania przychodu to 7 × 170 × 4,05 = 4 819,50 PLN.

**Kluczowa obserwacja:** każda paczka kupna ma **swój kurs NBP**. Nie możesz uśrednić kursów — przeliczasz każdą transzę osobno, po kursie z dnia roboczego poprzedzającego datę jej nabycia.

## Częściowa sprzedaż — jak liczyć? {#czesciowa-sprzedaz}

A co jeśli sprzedajesz mniej niż najstarszą paczkę? Wtedy „zużywasz" tylko część.

### Przykład

Masz:
- 10.02: 10 akcji po 100 PLN
- 15.05: 5 akcji po 120 PLN

Sprzedajesz 6 akcji po 150 PLN.

Metodą FIFO:
- Z paczki 10.02 „zużywasz" 6 akcji (z 10 dostępnych).
- Koszt: 6 × 100 = **600 PLN**
- Przychód: 6 × 150 = **900 PLN**
- Dochód: **300 PLN**

Na stanie zostaje:
- 10.02: **4 akcje** (niedokończona paczka) po 100 PLN
- 15.05: 5 akcji po 120 PLN

Przy następnej sprzedaży FIFO zacznie od tych 4 akcji z 10.02 — dopiero potem przejdzie do paczki z 15.05.

## Cross-broker FIFO {#cross-broker}

To pułapka, na którą wpada wielu inwestorów. **FIFO stosuje się globalnie per instrument, nie per broker.**

### Przykład

- 01.03 — kupujesz 10 AAPL przez [Revolut](/blog/rozliczenie-revolut) po 180 USD
- 15.06 — kupujesz 10 AAPL przez [Interactive Brokers](/blog/rozliczenie-interactive-brokers) po 190 USD
- 01.10 — sprzedajesz 10 AAPL przez Interactive Brokers po 200 USD

Intuicja podpowiada: "sprzedaję przez IB, więc koszt to 190 USD (bo tam kupiłem)".

**Błąd!** FIFO mówi: sprzedajesz najstarsze. Najstarsze to te z Revolut (01.03 po 180 USD). Koszt uzyskania przychodu = 10 × 180 USD, mimo że sprzedajesz "przez" IB.

### Dlaczego to jest problem?

Bo musisz **połączyć ewidencje ze wszystkich brokerów** w jedną chronologiczną listę transakcji per instrument. Jeśli rozliczasz każdego brokera osobno (jak osobne PIT-38), wynik będzie błędny.

### Jak to zrobić prawidłowo?

1. Wyeksportuj transakcje ze wszystkich brokerów.
2. Dla każdego instrumentu (ISIN) posortuj wszystkie kupna chronologicznie.
3. Stosuj FIFO globalnie.

W praktyce najczęstszy problem dotyczy par: Revolut + IB, [Degiro](/blog/rozliczenie-degiro) + XTB, itp.

## FIFO a różne waluty {#fifo-waluty}

FIFO stosuje się **per instrument**, niezależnie od waluty transakcji. Ale każda transakcja przeliczana jest na PLN po kursie NBP z właściwego dnia.

### Przykład

- 10.01: kupno 5 AAPL po 180 USD, kurs NBP 4,00 → koszt 3 600 PLN
- 10.06: kupno 5 AAPL po 180 USD, kurs NBP 4,20 → koszt 3 780 PLN
- 10.12: sprzedaż 5 AAPL po 180 USD, kurs NBP 4,10 → przychód 3 690 PLN

Cena w USD jest identyczna w obu kupnach i sprzedaży, ale:
- FIFO → koszt = 3 600 PLN (paczka ze stycznia, kurs 4,00)
- Przychód = 3 690 PLN
- Dochód = **90 PLN** — wyłącznie z różnic kursowych!

To pokazuje, że nawet bez zmiany ceny akcji w USD możesz mieć dochód (lub stratę) z samych różnic kursowych. I ten dochód podlega opodatkowaniu.

## FIFO a ułamkowe akcje {#fifo-ulamkowe}

Brokerzy tacy jak [Revolut](/blog/rozliczenie-revolut) czy Trading 212 pozwalają na kupno ułamkowych akcji. FIFO działa identycznie — liczy się chronologia, nie rozmiar paczki.

### Przykład

- 01.02: kupno 0,5 AAPL po 180 USD
- 15.03: kupno 0,3 AAPL po 190 USD
- 01.06: sprzedaż 0,6 AAPL po 200 USD

FIFO:
1. Cała paczka 01.02: 0,5 × 180 = 90 USD
2. Z paczki 15.03: 0,1 × 190 = 19 USD
3. Łączny koszt: 109 USD
4. Przychód: 0,6 × 200 = 120 USD
5. Dochód: 11 USD

Na stanie: 0,2 AAPL z 15.03 po 190 USD.

## Najczęstsze błędy {#najczestsze-bledy}

### 1. Stosowanie LIFO lub średniej

Niektórzy inwestorzy (i niestety niektóre kalkulatory internetowe) stosują LIFO lub średnią ważoną. W polskim PIT-38 dozwolone jest **wyłącznie FIFO**.

### 2. FIFO per broker zamiast globalnie

Jak pokazałem w sekcji o [cross-broker FIFO](#cross-broker) — to poważny błąd, który może zmienić kwotę podatku.

### 3. Ignorowanie prowizji

Prowizje brokerskie są częścią kosztu uzyskania przychodu. Prowizja przy kupnie **zwiększa** koszt. Prowizja przy sprzedaży **zmniejsza** przychód (lub jest dodatkowym kosztem). Nie pomijaj ich.

### 4. Zły kurs NBP

Każda paczka kupna ma swój kurs NBP (z dnia poprzedzającego kupno). Sprzedaż ma swój kurs (z dnia poprzedzającego sprzedaż). Nie uśredniaj kursów.

### 5. Pomylenie trade date i settlement date

FIFO opiera się na **dacie zawarcia transakcji** (trade date), nie dacie rozliczenia (settlement date, która jest zwykle T+2). Jeśli kupiłeś w środę, a rozliczenie było w piątek — liczy się środa.

### 6. Zapomnienie o corporate actions

Split akcji, reverse split, spin-off — te zdarzenia zmieniają liczbę akcji i cenę nabycia, ale nie zmieniają kolejności FIFO. Musisz je uwzględnić w ewidencji. Np. przy splicie 4:1 Twoje 10 akcji po 400 USD staje się 40 akcjami po 100 USD — ale data nabycia pozostaje ta sama.

### 7. Nieuwzględnienie straty w FIFO

Strata też podlega FIFO. Jeśli sprzedajesz ze stratą, koszt uzyskania przychodu (wyliczony metodą FIFO) jest wyższy niż przychód. Tę stratę możesz [odliczyć w kolejnych latach](/blog/strata-z-akcji-odliczenie).

## Jak TaxPilot liczy FIFO {#taxpilot}

Ręczne obliczenie FIFO jest wykonalne przy kilku transakcjach. Ale przy kilkudziesięciu lub kilkuset — z wielu brokerów, w wielu walutach, z ułamkowymi akcjami — to godziny żmudnej pracy i duże ryzyko błędu.

TaxPilot automatyzuje FIFO kompletnie:

1. **Import z wielu brokerów** — wgrywasz raporty z Revolut, IB, Degiro, XTB. System je łączy.
2. **Identyfikacja instrumentów** — mapowanie tickerów na ISIN, rozpoznanie splitów i corporate actions.
3. **Globalne FIFO** — sortowanie wszystkich transakcji chronologicznie per instrument, niezależnie od brokera.
4. **Kursy NBP** — automatycznie dla każdej paczki kupna i każdej sprzedaży.
5. **Ułamkowe akcje** — pełna precyzja do ułamków.
6. **Raport FIFO** — możesz zobaczyć, które paczki zostały "zużyte" przy każdej sprzedaży.

Nie musisz budować skomplikowanego Excela. Importujesz, weryfikujesz, wysyłasz.

[Wypróbuj TaxPilot za darmo →](https://taxpilot.pl)

---

## Podsumowanie

Metoda FIFO to jedyna dozwolona metoda rozliczania kosztów uzyskania przychodu w PIT-38. Najważniejsze zasady:

- **FIFO = najstarsze kupno odchodzi pierwsze.**
- **Art. 24 ust. 10 ustawy o PIT** — nie masz wyboru metody.
- **FIFO jest globalne** — per instrument, nie per broker.
- **Każda paczka kupna** ma swój kurs NBP i swoją cenę nabycia.
- **Prowizje** wchodzą w koszt uzyskania przychodu.
- **Przy częściowej sprzedaży** zużywasz część najstarszej paczki, reszta czeka.

Jeśli masz transakcje u kilku brokerów, [TaxPilot](https://taxpilot.pl) połączy je, zastosuje FIFO globalnie i wygeneruje gotowy PIT-38.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
