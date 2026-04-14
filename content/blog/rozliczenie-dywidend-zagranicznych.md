---
title: "Podatek od dywidend zagranicznych — jak rozliczyć w PIT-38?"
slug: rozliczenie-dywidend-zagranicznych
description: "Jak rozliczyć dywidendy zagraniczne w PIT-38? Dowiedz się o podatku u źródła (WHT), umowach o unikaniu podwójnego opodatkowania, formularzach PIT/ZG i W-8BEN. Przykłady dla USA, Niemiec i UK."
date: 2026-11-05
keywords: [rozliczenie dywidend zagranicznych, podatek od dywidend, pit/zg, dywidendy zagraniczne pit-38, podatek u źródła dywidendy, w-8ben, unikanie podwójnego opodatkowania]
schema: Article
---

# Podatek od dywidend zagranicznych — jak rozliczyć w PIT-38?

Dostajesz dywidendy z Apple, Siemensa albo Unilever? Gratulacje — to przyjemna część inwestowania. Mniej przyjemna? Polski urząd skarbowy chce swoją część. A sposób rozliczenia dywidend zagranicznych potrafi przyprawić o ból głowy nawet doświadczonych inwestorów.

W tym artykule wyjaśniam krok po kroku, jak działa opodatkowanie dywidend zagranicznych, czym jest podatek u źródła (WHT), jak korzystać z umów o unikaniu podwójnego opodatkowania (UPO) i jak prawidłowo wypełnić PIT/ZG. Na końcu — konkretne przykłady dla trzech najpopularniejszych rynków: USA, Niemcy i UK.

> Ten artykuł jest częścią kompletnego poradnika: [Jak rozliczyć PIT-38 z inwestycji zagranicznych](/blog/rozliczenie-pit-38-inwestycje-zagraniczne)

## Spis treści

1. [Jak są opodatkowane dywidendy w Polsce?](#opodatkowanie-dywidend-polska)
2. [Podatek u źródła (WHT) — co to jest?](#podatek-u-zrodla)
3. [Umowy o unikaniu podwójnego opodatkowania (UPO)](#upo)
4. [Metoda odliczenia proporcjonalnego](#metoda-odliczenia)
5. [Formularz PIT/ZG — jak wypełnić](#pit-zg)
6. [W-8BEN — zmniejsz WHT z USA do 15%](#w-8ben)
7. [Przykład 1: Dywidenda z USA (Apple)](#przyklad-usa)
8. [Przykład 2: Dywidenda z Niemiec (Siemens)](#przyklad-niemcy)
9. [Przykład 3: Dywidenda z UK (Unilever)](#przyklad-uk)
10. [PIT/ZG — osobny dla każdego kraju](#pit-zg-kraje)
11. [Kursy NBP przy dywidendach](#kursy-nbp)
12. [Najczęstsze błędy](#najczestsze-bledy)
13. [Jak TaxPilot rozlicza dywidendy automatycznie](#taxpilot)

---

## Jak są opodatkowane dywidendy w Polsce? {#opodatkowanie-dywidend-polska}

W Polsce dywidendy opodatkowane są **zryczałtowanym podatkiem 19%**. Dotyczy to zarówno dywidend polskich, jak i zagranicznych.

Kluczowa różnica:

- **Dywidendy polskie** — podatek potrąca broker/biuro maklerskie. Dostajesz kwotę netto, nie musisz nic robić.
- **Dywidendy zagraniczne** — podatek potrąca kraj źródła (WHT), a Ty musisz dopłacić różnicę w Polsce i wykazać to w zeznaniu.

Przy dywidendach zagranicznych masz więc do czynienia z **dwoma jurysdykcjami podatkowymi** — i to jest sedno problemu.

## Podatek u źródła (WHT) — co to jest? {#podatek-u-zrodla}

WHT (Withholding Tax) to podatek potrącany automatycznie przez kraj, w którym spółka wypłaca dywidendę. Zanim pieniądze trafią na Twoje konto, kraj źródła zabiera swoją część.

### Stawki WHT w najpopularniejszych krajach

| Kraj | Stawka domyślna | Stawka po UPO z Polską |
|------|-----------------|----------------------|
| **USA** | 30% | 15% (z W-8BEN) |
| **Niemcy** | 26,375% | 15% |
| **UK** | 0% | 0% |
| **Holandia** | 15% | 15% |
| **Irlandia** | 25% | 15% |
| **Francja** | 25% | 15% |
| **Szwajcaria** | 35% | 15% |

Widzisz problem? Jeśli nie zadbasz o odpowiednie formularze, kraj źródła może pobrać znacznie więcej niż wynika z umowy bilateralnej. W USA bez formularza W-8BEN zapłacisz 30% zamiast 15%.

## Umowy o unikaniu podwójnego opodatkowania (UPO) {#upo}

Polska podpisała umowy UPO z ponad 90 krajami. Ich cel jest prosty: zapobiec sytuacji, w której ten sam dochód jest opodatkowany podwójnie — raz w kraju źródła, raz w kraju rezydencji.

### Jak UPO działa dla dywidend?

Typowa umowa UPO mówi:

1. Kraj źródła **może** pobrać WHT, ale do określonego limitu (najczęściej 15%).
2. Kraj rezydencji (Polska) **odlicza** pobrany WHT od swojego podatku.

To oznacza, że nie płacisz podwójnie. Ale — i to jest kluczowe — **musisz to rozliczyć samodzielnie** w zeznaniu podatkowym. Nikt tego za Ciebie nie zrobi.

### Gdzie znaleźć tekst UPO?

Wszystkie umowy UPO są publikowane w Dzienniku Ustaw. Znajdziesz je na stronie Ministerstwa Finansów lub w bazie ISAP. W praktyce dla inwestora giełdowego najważniejszy jest artykuł dotyczący dywidend — zwykle art. 10 umowy.

## Metoda odliczenia proporcjonalnego {#metoda-odliczenia}

Polska stosuje wobec dywidend **metodę odliczenia proporcjonalnego** (a nie zwolnienia z progresją). Co to oznacza w praktyce?

1. Naliczasz polski podatek 19% od **brutto** dywidendy.
2. Odliczasz WHT pobrany za granicą.
3. Dopłacasz różnicę.

### Ograniczenie odliczenia

Możesz odliczyć WHT tylko do wysokości polskiego podatku od tego dochodu. Innymi słowy:

- Jeśli WHT = 15%, a polski podatek = 19% → dopłacasz 4%.
- Jeśli WHT = 15%, a polski podatek = 19% → odliczasz pełne 15%.
- Jeśli WHT = 30% (bo nie złożyłeś W-8BEN), a polski podatek = 19% → odliczasz tylko 19%, a 11% „przepada".

To ostatni scenariusz to najczęstsza pułapka. Jeśli kraj źródła pobrał więcej niż 19%, nadwyżki **nie odzyskasz** przez polskie zeznanie. Dlatego W-8BEN i prawidłowe stawki UPO są tak ważne.

## Formularz PIT/ZG — jak wypełnić {#pit-zg}

PIT/ZG to załącznik do PIT-38 (lub PIT-36), w którym wykazujesz dochody zagraniczne. Dla dywidend jest to kluczowy dokument.

### Co wpisać w PIT/ZG?

- **Kraj uzyskania dochodu** — wybierasz z listy (np. Stany Zjednoczone).
- **Przychód** — kwota brutto dywidendy w PLN (przeliczona po kursie NBP).
- **Podatek zapłacony za granicą** — kwota WHT w PLN.

### PIT/ZG a PIT-38 — jak się łączą?

Kwoty z PIT/ZG przenosisz do odpowiednich rubryk PIT-38. W sekcji dotyczącej dochodów zagranicznych wpisujesz łączny przychód z dywidend i łączny podatek zapłacony za granicą.

**Ważne:** Potrzebujesz **osobnego PIT/ZG dla każdego kraju**. Jeśli dostajesz dywidendy z USA, Niemiec i UK — składasz trzy załączniki PIT/ZG.

## W-8BEN — zmniejsz WHT z USA do 15% {#w-8ben}

W-8BEN to formularz IRS (amerykańskiego urzędu skarbowego), który potwierdzasz swoją rezydencję podatkową poza USA. Dzięki niemu kwalifikujesz się do obniżonej stawki WHT wynikającej z UPO.

### Dlaczego W-8BEN jest tak ważny?

Bez W-8BEN:
- USA pobiera **30% WHT** od dywidendy.
- W Polsce możesz odliczyć tylko 19%.
- Tracisz **11% bezpowrotnie**.

Z W-8BEN:
- USA pobiera **15% WHT**.
- W Polsce dopłacasz 4%.
- Łączne obciążenie = 19%.

### Jak złożyć W-8BEN?

Większość brokerów pozwala złożyć W-8BEN elektronicznie:

- **Interactive Brokers** — w ustawieniach konta, sekcja "Tax Forms".
- **Degiro** — wypełniasz przy otwarciu konta (od 2023).
- **Revolut** — automatycznie przy włączeniu handlu akcjami US.
- **Trading 212** — automatycznie przy rejestracji.

W-8BEN jest ważny przez **3 lata**. Po wygaśnięciu musisz go odnowić, inaczej wracasz do 30% WHT.

## Przykład 1: Dywidenda z USA (Apple) {#przyklad-usa}

Załóżmy, że w 2026 roku Apple wypłacił Ci dywidendę brutto **1 000 USD**. Masz złożony W-8BEN.

### Krok 1: WHT w USA

USA pobiera 15% WHT:
- 1 000 USD × 15% = **150 USD pobranego WHT**
- Na konto trafia: 850 USD

### Krok 2: Przeliczenie na PLN

Kurs średni NBP z dnia roboczego poprzedzającego dzień wypłaty dywidendy. Załóżmy kurs 4,05 PLN/USD.

- Dywidenda brutto: 1 000 × 4,05 = **4 050 PLN**
- WHT: 150 × 4,05 = **607,50 PLN**

### Krok 3: Polski podatek

- 19% od brutto: 4 050 × 19% = **769,50 PLN**
- Minus WHT: 769,50 − 607,50 = **162 PLN do dopłaty**

### Krok 4: PIT/ZG

W PIT/ZG dla USA wpisujesz:
- Przychód: 4 050 PLN
- Podatek zapłacony za granicą: 607,50 PLN (zaokrąglony do 608 PLN)

**Łączne obciążenie:** 150 USD (WHT) + 40 USD (dopłata w PL) = 190 USD, czyli 19% od brutto. Tak powinno wyglądać prawidłowe rozliczenie.

## Przykład 2: Dywidenda z Niemiec (Siemens) {#przyklad-niemcy}

Siemens wypłacił Ci dywidendę brutto **500 EUR**. UPO Polska-Niemcy przewiduje 15% WHT.

### Krok 1: WHT w Niemczech

Tutaj jest subtelność. Niemiecka stawka bazowa to 25% + 5,5% Solidaritätszuschlag = **26,375%**. Ale na mocy UPO Polska-Niemcy, stawka wynosi 15%.

Czy broker automatycznie zastosuje 15%? **Zależy od brokera.** Niektórzy (jak Interactive Brokers) stosują stawkę UPO automatycznie. Inni pobierają pełną stawkę, a Ty musisz ubiegać się o zwrot nadpłaty w niemieckim urzędzie skarbowym (Bundeszentralamt für Steuern).

Zakładamy optymistyczny scenariusz — broker zastosował 15%:
- 500 EUR × 15% = **75 EUR WHT**
- Na konto: 425 EUR

### Krok 2: Przeliczenie na PLN

Kurs NBP z dnia poprzedzającego. Załóżmy 4,32 PLN/EUR.

- Brutto: 500 × 4,32 = **2 160 PLN**
- WHT: 75 × 4,32 = **324 PLN**

### Krok 3: Polski podatek

- 19%: 2 160 × 19% = **410,40 PLN**
- Minus WHT: 410,40 − 324 = **86,40 PLN → 86 PLN do dopłaty**

### A jeśli broker pobrał 26,375%?

- WHT: 500 × 26,375% = 131,88 EUR → 569,72 PLN
- Polski podatek: 410,40 PLN
- Odliczasz: **410,40 PLN** (nie więcej niż polski podatek)
- Dopłata w PL: 0 PLN
- Stracone: 569,72 − 410,40 = **159,32 PLN** (chyba że złożysz wniosek o zwrot do BZSt)

Dlatego warto upewnić się, że broker stosuje prawidłową stawkę UPO.

## Przykład 3: Dywidenda z UK (Unilever) {#przyklad-uk}

UK jest wyjątkowym przypadkiem. Od 2016 roku Wielka Brytania **nie pobiera WHT od dywidend**. Zero.

Unilever wypłacił Ci 300 GBP brutto.

### Krok 1: WHT

- WHT: **0 GBP**
- Na konto: 300 GBP (pełna kwota)

### Krok 2: Przeliczenie na PLN

Kurs NBP: 5,10 PLN/GBP.

- Brutto: 300 × 5,10 = **1 530 PLN**

### Krok 3: Polski podatek

- 19%: 1 530 × 19% = **290,70 PLN → 291 PLN do dopłaty**
- Brak WHT do odliczenia → cały podatek płacisz w Polsce.

### Krok 4: PIT/ZG

Nadal składasz PIT/ZG dla UK! Nawet przy zerowym WHT — musisz wykazać dochód zagraniczny.

**Wniosek:** Dywidendy z UK są „czyste" — nie tracisz na nadpłaconym WHT. Ale oznacza to też, że pełne 19% płacisz sam do polskiego urzędu.

## PIT/ZG — osobny dla każdego kraju {#pit-zg-kraje}

To jeden z najczęściej pomijanych wymogów. Jeśli dostajesz dywidendy z trzech krajów, składasz **trzy** załączniki PIT/ZG do PIT-38.

### Przykład: inwestor z dywidendami z USA, DE i UK

| PIT/ZG | Kraj | Przychód (PLN) | WHT (PLN) |
|--------|------|----------------|-----------|
| Nr 1 | USA | 4 050 | 608 |
| Nr 2 | Niemcy | 2 160 | 324 |
| Nr 3 | UK | 1 530 | 0 |

W PIT-38 wpisujesz **sumę** ze wszystkich PIT/ZG:
- Łączny przychód z dywidend zagranicznych: 7 740 PLN
- Łączny WHT do odliczenia: 932 PLN

### A co z akcjami sprzedanymi na giełdzie zagranicznej?

Dochód ze sprzedaży akcji też wykazujesz w PIT/ZG — ale w innej sekcji niż dywidendy. PIT/ZG ma oddzielne rubryki dla:
- Dochodów z odpłatnego zbycia papierów wartościowych
- Dochodów z dywidend i innych przychodów z tytułu udziału w zyskach

Więcej o sprzedaży akcji znajdziesz w [poradniku o PIT-38](/blog/rozliczenie-pit-38-inwestycje-zagraniczne).

## Kursy NBP przy dywidendach {#kursy-nbp}

Zasada jest taka sama jak przy sprzedaży akcji: stosujesz **średni kurs NBP z ostatniego dnia roboczego poprzedzającego dzień uzyskania przychodu**.

### Który dzień jest „dniem uzyskania przychodu"?

Dla dywidend to **payment date** (dzień wypłaty), nie record date ani ex-dividend date. To dzień, w którym pieniądze trafiają na Twoje konto brokerskie.

### Praktyczny problem

Przy kilkudziesięciu dywidendach rocznie (np. z ETF-ów kwartalnych lub spółek dywidendowych) ręczne sprawdzanie kursów NBP dla każdej wypłaty to koszmar. Każda dywidenda może mieć inny kurs, inną datę, inną walutę.

To jeden z powodów, dla których inwestorzy korzystają z automatycznych narzędzi — ale o tym za chwilę.

## Najczęstsze błędy {#najczestsze-bledy}

### 1. Brak W-8BEN przy inwestycjach w USA

Bez W-8BEN tracisz 11% dywidendy bezpowrotnie. To nie jest kwestia optymalizacji — to kwestia niepłacenia więcej, niż musisz.

### 2. Brak PIT/ZG

Dywidendy zagraniczne **muszą** być wykazane w załączniku PIT/ZG. Sam PIT-38 nie wystarczy. Brak PIT/ZG to formalny błąd, który urząd skarbowy może zakwestionować.

### 3. Jeden PIT/ZG dla wielu krajów

Każdy kraj wymaga **osobnego** PIT/ZG. Nie łączysz dywidend z USA i Niemiec w jednym załączniku.

### 4. Zły kurs NBP

Częsty błąd: wzięcie kursu z dnia wypłaty zamiast z dnia poprzedzającego. Albo użycie kursu kupna/sprzedaży zamiast średniego.

### 5. Odliczenie WHT powyżej limitu

Jeśli kraj źródła pobrał 30%, a polski podatek wynosi 19% — odliczasz **maksymalnie 19%**, nie 30%. Nadwyżka nie przechodzi na następny rok.

### 6. Ignorowanie dywidend z UK

"UK nie pobiera WHT, więc nie muszę tego wykazywać" — błąd. Dochód jest dochodem. Musisz go wykazać w PIT-38 i PIT/ZG, a pełne 19% zapłacić samodzielnie.

### 7. Mieszanie dywidend z zyskami kapitałowymi

Dywidendy i zyski ze sprzedaży akcji to **różne źródła przychodów**. W PIT-38 wykazujesz je w osobnych sekcjach. Nie łącz ich.

## Jak TaxPilot rozlicza dywidendy automatycznie {#taxpilot}

Ręczne rozliczanie dywidend z kilku krajów, w kilku walutach, z różnymi stawkami WHT i kursami NBP — to godziny żmudnej pracy. I spore ryzyko błędu.

TaxPilot automatyzuje cały proces:

1. **Import raportu z brokera** — wgrywasz plik CSV z Interactive Brokers, Degiro, [Revolut](/blog/rozliczenie-revolut) lub Bossa.
2. **Automatyczne rozpoznanie dywidend** — system identyfikuje wypłaty dywidend, WHT i kraj źródła.
3. **Kursy NBP** — automatyczne pobranie właściwych kursów dla każdej wypłaty.
4. **Zastosowanie UPO** — system zna stawki WHT z umów bilateralnych i weryfikuje, czy broker pobrał prawidłową kwotę.
5. **Generowanie PIT/ZG** — osobny załącznik dla każdego kraju, z prawidłowymi kwotami.
6. **Gotowy PIT-38** — kompletny formularz z sekcją dywidend zagranicznych, do wgrania na e-Deklaracje.

Nie musisz znać stawek UPO, szukać kursów NBP ani liczyć odliczeń. Importujesz, weryfikujesz, wysyłasz.

[Wypróbuj TaxPilot za darmo →](https://taxpilot.pl)

---

## Podsumowanie

Rozliczenie dywidend zagranicznych wymaga zrozumienia trzech mechanizmów: podatku u źródła (WHT), umów o unikaniu podwójnego opodatkowania (UPO) i metody odliczenia proporcjonalnego. Najważniejsze zasady:

- **19% podatku** w Polsce od dywidendy brutto.
- **WHT odliczasz** od polskiego podatku — ale nie więcej niż 19%.
- **W-8BEN** to must-have dla inwestycji w USA — bez niego tracisz 11%.
- **PIT/ZG** — osobny dla każdego kraju, obowiązkowy nawet przy zerowym WHT.
- **Kurs NBP** — średni, z dnia roboczego poprzedzającego dzień wypłaty.

Jeśli masz dywidendy z kilku krajów i kilkudziesięciu spółek, [TaxPilot](/blog/rozliczenie-pit-38-inwestycje-zagraniczne) wygeneruje Ci kompletny PIT-38 z PIT/ZG w kilka minut.

---

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
