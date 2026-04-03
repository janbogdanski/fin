---
title: "PIT-8C a PIT-38 — czym się różnią i kiedy który?"
slug: pit-8c-vs-pit-38-roznice
description: "PIT-8C to informacja od brokera, PIT-38 to Twoje zeznanie podatkowe. Dowiedz się kiedy dostajesz PIT-8C, a kiedy musisz rozliczyć się samodzielnie."
date: 2026-10-15
keywords: [pit 8c a pit-38 roznice, pit-8c, pit-38, rozliczenie akcji]
schema: Article
---

# PIT-8C a PIT-38 — czym się różnią i kiedy który?

Jeśli inwestujesz na giełdzie, prędzej czy później spotkasz się z dwoma formularzami: PIT-8C i PIT-38. Choć brzmią podobnie, pełnią zupełnie różne funkcje. Wyjaśniamy.

## PIT-8C — informacja od brokera

PIT-8C to **informacja**, którą broker (lub dom maklerski) wysyła do Ciebie i do Urzędu Skarbowego. Nie jest to zeznanie podatkowe — to raport o Twoich transakcjach.

**Kto wysyła PIT-8C:**
- XTB
- mBank eMakler
- Bossa
- Każdy polski dom maklerski z siedzibą w PL

**Co zawiera:**
- Przychody ze sprzedaży papierów wartościowych
- Koszty uzyskania przychodu (kupno + prowizje)
- Już przeliczone na PLN (dla instrumentów PLN)

**Kiedy dostajesz:** Do końca lutego za poprzedni rok podatkowy.

## PIT-38 — Twoje zeznanie podatkowe

PIT-38 to **zeznanie podatkowe**, które Ty składasz do Urzędu Skarbowego. To Twoja odpowiedzialność — nawet jeśli nie dostałeś PIT-8C.

**Kto składa PIT-38:**
- Każdy, kto sprzedał papiery wartościowe, udziały, lub kryptowaluty
- Niezależnie od tego, czy miał zysk czy stratę
- Termin: do 30 kwietnia

**Co zawiera:**
- Sekcja C: przychody i koszty z akcji/ETF/pochodnych
- Sekcja D: dywidendy zagraniczne (z PIT/ZG)
- Sekcja E: kryptowaluty
- Obliczony podatek (19% od zysku)

## Kluczowa różnica

| | PIT-8C | PIT-38 |
|---|---|---|
| **Co to jest** | Informacja od brokera | Zeznanie podatkowe |
| **Kto tworzy** | Broker | Ty (podatnik) |
| **Kto wysyła do US** | Broker (automatycznie) | Ty (przez e-Deklaracje) |
| **Obowiązkowy** | Broker musi wysłać | Ty musisz złożyć |
| **Termin** | Do końca lutego | Do 30 kwietnia |

## Problem: zagraniczny broker NIE wyśle PIT-8C

Oto kluczowy punkt: **Interactive Brokers, Degiro, Revolut, eToro, Trading212** — żaden z nich nie wysyła PIT-8C. Nie mają takiego obowiązku, bo nie są polskimi podmiotami.

To oznacza, że musisz:
1. Sam pobrać raport transakcji z brokera (CSV)
2. Sam przeliczyć każdą transakcję na PLN po kursie NBP
3. Sam zastosować metodę FIFO (Pierwsze Weszło, Pierwsze Wyszło)
4. Sam obliczyć prowizje jako koszt uzyskania przychodu
5. Sam wypełnić PIT-38 i wysłać do Urzędu Skarbowego

Przy 10 transakcjach to godzina pracy. Przy 200 — cały weekend z Excelem.

## Co zrobić gdy masz brokera polskiego I zagranicznego?

Typowy scenariusz: XTB (PL) + Interactive Brokers (zagraniczny).

1. **XTB** wyśle Ci PIT-8C z polskimi transakcjami
2. **IBKR** nie wyśle niczego — musisz sam rozliczyć
3. W PIT-38 **łączysz oba źródła**: dane z PIT-8C + własne obliczenia z IBKR
4. **FIFO jest wspólne** — jeśli masz te same akcje (np. Apple) w XTB i IBKR, kolejka FIFO jest jedna per instrument

To ostatni punkt jest najczęściej pomijany. FIFO nie jest per broker — jest per ISIN (numer identyfikujący instrument).

## Jak TaxPilot to upraszcza

[TaxPilot](/) importuje dane z obu źródeł:
- CSV z Interactive Brokers, Degiro, Revolut, Bossa
- Automatycznie przelicza na PLN po kursach NBP
- Stosuje FIFO cross-broker (łączy pozycje z różnych brokerów)
- Generuje gotowy PIT-38 XML do e-Deklaracji

30 transakcji za darmo. Bez Excela, bez stresu.

## FAQ

**Czy muszę składać PIT-38 jeśli miałem tylko stratę?**
Tak. Stratę warto wykazać — możesz ją odliczyć w kolejnych 5 latach (do 50% rocznie).

**Czy PIT-8C zwalnia mnie z PIT-38?**
Nie. PIT-8C to informacja, PIT-38 to zeznanie. Musisz złożyć PIT-38 nawet jeśli dostałeś PIT-8C.

**Co jeśli dane z PIT-8C się nie zgadzają z moimi obliczeniami?**
Skontaktuj się z brokerem. Jeśli różnica jest niewielka (grosze z zaokrągleń) — użyj swoich obliczeń i zachowaj dokumentację.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. Przed złożeniem deklaracji skonsultuj się z doradcą podatkowym.*
