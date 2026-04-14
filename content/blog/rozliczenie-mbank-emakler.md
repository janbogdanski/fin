---
title: "Rozliczenie mBank eMakler PIT-38 — jak korzystać z PIT-8C i co zrobić z akcjami zagranicznymi?"
slug: rozliczenie-mbank-emakler
description: "Rozliczenie mBank eMakler PIT-38 2025: jak użyć PIT-8C dla akcji z GPW, co zrobić z zagranicznymi ETF i akcjami, kiedy liczyć kurs NBP. Krok po kroku."
date: 2026-04-04
keywords: [rozliczenie mbank emakler pit-38 2025, mbank emakler pit-8c, mbank emakler akcje zagraniczne, mbank emakler csv, emakler rozliczenie podatkowe, mbank pit-38 2025]
schema: Article
---

# Rozliczenie mBank eMakler PIT-38 — jak korzystać z PIT-8C i co zrobić z akcjami zagranicznymi?

mBank eMakler to polski dom maklerski — i to robi zasadniczą różnicę w porównaniu z brokerami zagranicznymi. Dla transakcji na Giełdzie Papierów Wartościowych w Warszawie (GPW) eMakler **wystawia PIT-8C** i przekazuje go zarówno Tobie, jak i urzędowi skarbowemu. Dla części transakcji zagranicznych sytuacja jest bardziej złożona.

Po przeczytaniu tego artykułu będziesz wiedział: jak PIT-8C z mBank eMakler przenosi się do PIT-38, kiedy masz obowiązek samodzielnie liczyć przychody i kursy NBP, oraz co zrobić gdy handlujesz zagranicznymi akcjami lub ETF przez eMakler. Na końcu opisuję aktualny stan wsparcia TaxPilot dla danych z PIT-8C.

Kwestia, która sprawia najwięcej kłopotów: PIT-8C pokrywa transakcje na GPW w PLN — ale jeśli przez eMakler handlowałeś akcjami notowanymi w EUR lub USD (np. przez rynek zagraniczny), PIT-8C może **nie obejmować** tych transakcji lub obejmować je tylko częściowo. Musisz to zweryfikować.

## Spis treści

1. [Krok 1 — Pobierz PIT-8C z mBank eMakler](#krok-1)
2. [Krok 2 — Jak PIT-8C trafia do PIT-38?](#krok-2)
3. [Krok 3 — Akcje i ETF zagraniczne — kiedy PIT-8C nie wystarczy](#krok-3)
4. [Krok 4 — Przeliczenie walut obcych (kurs NBP)](#krok-4)
5. [Krok 5 — Import do TaxPilot](#krok-5)
6. [Najczęstsze pytania](#faq)

---

## Krok 1 — Pobierz PIT-8C z mBank eMakler {#krok-1}

mBank eMakler jest płatnikiem podatkowym w rozumieniu art. 39 ust. 3 ustawy o PIT. Wystawia PIT-8C do końca lutego za poprzedni rok podatkowy.

### Gdzie znaleźć PIT-8C

1. Zaloguj się na [mbank.pl](https://www.mbank.pl) i przejdź do sekcji **eMakler**.
2. W menu eMakler znajdź sekcję **Dokumenty** lub **Rozliczenia** → **PIT-8C**.
3. Pobierz dokument za rok 2025 w formacie PDF.

Alternatywnie: mBank przesyła PIT-8C pocztą lub elektronicznie do skrzynki w systemie banku — sprawdź powiadomienia.

[Ilustracja: Widok sekcji "Dokumenty" w panelu eMakler mBank — zakładka "PIT-8C", rok 2025, przycisk pobierania PDF]

### Co zawiera PIT-8C z mBank eMakler

PIT-8C z eMakler zawiera zestawienie:

| Sekcja w PIT-8C | Znaczenie |
|---|---|
| Sekcja D — Przychody ze sprzedaży | Przychody ze sprzedaży papierów wartościowych (w PLN) |
| Sekcja E — Koszty uzyskania przychodów | Koszty nabycia sprzedanych papierów (w PLN) |
| Dochód / Strata | Wynik netto (w PLN) |

Kwoty w PIT-8C są już w PLN — dla transakcji na GPW dom maklerski przelicza sam lub transakcje są bezpośrednio w PLN. **Nie musisz stosować kursów NBP do kwot podanych w PIT-8C.**

**PIT-8C zawiera dane już przeliczone na PLN — przepisz je do PIT-38 bez modyfikacji.**

---

## Krok 2 — Jak PIT-8C trafia do PIT-38? {#krok-2}

Masz PIT-8C z mBank eMakler. Oto co z nim robisz.

### Przepisanie danych do PIT-38

Dane z PIT-8C przepisujesz do **sekcji C i D** formularza PIT-38:

1. **Sekcja C PIT-38 — Przychody ze sprzedaży papierów wartościowych:** wpisz kwotę z sekcji D PIT-8C (przychody ze sprzedaży).
2. **Sekcja D PIT-38 — Koszty uzyskania przychodów:** wpisz kwotę z sekcji E PIT-8C (koszty nabycia).
3. **Dochód lub strata** oblicza się automatycznie: Przychody − Koszty.

Jeśli masz PIT-8C z więcej niż jednego domu maklerskiego (np. eMakler + [Degiro](/blog/rozliczenie-degiro)) — sumujesz przychody i koszty ze wszystkich PIT-8C w tej samej sekcji.

Przykład:

| Źródło | Przychody (PLN) | Koszty (PLN) | Dochód (PLN) |
|---|---|---|---|
| PIT-8C mBank eMakler — GPW | 24 500,00 | 21 200,00 | 3 300,00 |
| Transakcje zagraniczne (samodzielnie obliczone) | 8 600,00 | 7 900,00 | 700,00 |
| **Łącznie w PIT-38** | **33 100,00** | **29 100,00** | **4 000,00** |

Podatek: 4 000,00 × 19% = 760,00 PLN (art. 30b ust. 1 ustawy o PIT).

### Zasada FIFO przy PIT-8C

mBank eMakler stosuje zasadę [FIFO (pierwsze weszło, pierwsze wyszło)](/blog/metoda-fifo-pit-38) przy obliczaniu kosztów nabycia — zgodnie z art. 24 ust. 10 ustawy o PIT. Kwoty kosztów w PIT-8C już to uwzględniają. Nie musisz przeliczać FIFO ręcznie dla transakcji pokrytych PIT-8C.

**Jeśli Twoje jedyne transakcje w 2025 roku to transakcje na GPW przez eMakler — PIT-8C z mBank to jedyne co potrzebujesz do wypełnienia PIT-38.**

---

## Krok 3 — Akcje i ETF zagraniczne — kiedy PIT-8C nie wystarczy {#krok-3}

mBank eMakler umożliwia handel instrumentami zagranicznymi — akcjami i ETF notowanymi na giełdach europejskich i światowych. **Zakres PIT-8C dla transakcji zagranicznych jest niejednoznaczny i wymaga weryfikacji.**

### Sprawdź co jest w Twoim PIT-8C

Otwórz PIT-8C i sprawdź szczegółowe zestawienie transakcji (jeśli jest dołączone). Zidentyfikuj:
- Czy widoczne są tylko instrumenty notowane na GPW?
- Czy są instrumenty w EUR, USD lub GBP?
- Czy dla instrumentów zagranicznych podane są kursy przeliczenia?

[DO LEGAL REVIEW: weryfikacja, czy mBank eMakler wystawia PIT-8C dla transakcji na rynkach zagranicznych (np. Xetra, Euronext) realizowanych przez platformę eMakler — czy broker ma obowiązek i czy faktycznie oblicza kurs NBP dla walut obcych przy PIT-8C dla tych transakcji]

### Kiedy musisz samodzielnie obliczyć przychody zagraniczne

Jeśli Twoje PIT-8C **nie obejmuje** transakcji zagranicznych (nie ma ich w zestawieniu), musisz:

1. Pobrać historię transakcji z mBank eMakler jako CSV lub wyciąg.
2. Dla każdej sprzedaży zagranicznego instrumentu obliczyć przychód w PLN (cena sprzedaży × ilość × kurs NBP z dnia poprzedzającego sprzedaż).
3. Dla każdego kupna obliczyć koszt w PLN (cena kupna × ilość × kurs NBP z dnia poprzedzającego kupno).
4. Zastosować FIFO (art. 24 ust. 10 ustawy o PIT) jeśli kupowałeś ten sam instrument wielokrotnie.
5. Zsumować wyniki i dodać do sekcji C i D PIT-38 obok danych z PIT-8C.

### Jak pobrać historię transakcji z mBank eMakler

1. Zaloguj się w eMakler → sekcja **Historia transakcji** lub **Zlecenia**.
2. Ustaw zakres dat: **01.01.2025 — 31.12.2025**.
3. Eksportuj do formatu CSV lub XLS.

**Nie zakładaj, że PIT-8C z eMakler pokrywa wszystkie Twoje transakcje — sprawdź to bezpośrednio w dokumencie.**

---

## Krok 4 — Przeliczenie walut obcych (kurs NBP) {#krok-4}

Ten krok dotyczy wyłącznie transakcji zagranicznych, które **nie są** objęte PIT-8C. Dla transakcji w PLN z GPW — kwoty z PIT-8C są już w PLN.

Zasada: każda transakcja w walucie obcej przeliczana jest po **średnim kursie NBP z dnia roboczego poprzedzającego datę transakcji** (art. 11a ust. 1 ustawy o PIT).

### Przykład — ETF zagraniczny w EUR

**Kupno: 10.01.2025, 8 szt. CSPX (iShares Core S&P 500 UCITS ETF), cena 522,00 EUR**
- Poprzedni dzień roboczy: 09.01.2025
- Kurs NBP EUR/PLN z 09.01.2025: **4,2794**
- Koszt: 8 × 522,00 × 4,2794 = **17.837,87 PLN**

**Sprzedaż: 05.09.2025, 8 szt. CSPX, cena 598,00 EUR**
- Poprzedni dzień roboczy: 04.09.2025
- Kurs NBP EUR/PLN z 04.09.2025: **4,2553**
- Przychód: 8 × 598,00 × 4,2553 = **20.353,36 PLN**

**Dochód = 20.353,36 − 17.837,87 = 2.515,49 PLN**

Te wartości trafiają do PIT-38 sekcja C i D — zsumowane z danymi z PIT-8C.

### Prowizja jako koszt

Jeśli w historii transakcji mBank eMakler widoczna jest osobna prowizja maklerska za transakcję zagraniczną (w EUR lub PLN) — **dolicza się ją do kosztu nabycia**. Prowizja zwiększa koszt, co zmniejsza dochód i podatek. Sprawdź wyciąg — prowizja może być wliczona w cenę lub pobrana osobno.

**Przeliczasz tylko transakcje zagraniczne nieujęte w PIT-8C. Nie przeliczaj ponownie transakcji, które mBank eMakler już uwzględnił w PIT-8C.**

---

## Krok 5 — Import do TaxPilot {#krok-5}

**TaxPilot nie obsługuje aktualnie bezpośredniego importu PIT-8C.** Danych z PIT-8C nie wgraj jako pliku do TaxPilot — format PDF nie jest przetwarzany automatycznie.

### Jak użyć TaxPilot przy rozliczeniu mBank eMakler

**Jeśli masz wyłącznie PIT-8C (tylko transakcje na GPW):**

1. Zaloguj się na [taxpilot.pl](https://taxpilot.pl) i utwórz nowe rozliczenie za rok 2025.
2. W sekcji ręcznego wprowadzania danych wpisz przychody i koszty z PIT-8C bezpośrednio.
3. TaxPilot wygeneruje PIT-38 z tymi kwotami i obliczy należny podatek.

**Jeśli masz też transakcje zagraniczne (poza PIT-8C):**

1. Wprowadź transakcje zagraniczne ręcznie w TaxPilot — import pliku CSV z mBank eMakler nie jest aktualnie obsługiwany. TaxPilot pobierze kursy NBP automatycznie dla każdej wprowadzonej transakcji.
2. Uzupełnij dane z PIT-8C ręcznie w odpowiednich sekcjach.
3. TaxPilot zsumuje oba źródła i wygeneruje gotowy PIT-38.

### Co TaxPilot automatyzuje przy rozliczeniu zagranicznych transakcji

Nawet przy ręcznym wprowadzeniu transakcji, TaxPilot:
- pobiera kursy NBP z archiwum NBP dla każdej wprowadzonej daty,
- stosuje FIFO do transakcji na tym samym instrumencie,
- generuje PIT-38 w formacie XML do wgrania na e-Deklaracje.

[Rozlicz transakcje z mBank eMakler z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania {#faq}

### Mam tylko PIT-8C z mBank eMakler i żadnych innych transakcji. Co wypełniam w PIT-38?

Przepisujesz kwoty z PIT-8C do sekcji C (przychody) i D (koszty) formularza PIT-38. Urząd skarbowy już otrzymał PIT-8C od mBank eMakler, więc dane muszą się zgadzać. Podatek oblicza się automatycznie: (przychody − koszty) × 19% (art. 30b ust. 1 ustawy o PIT). Termin złożenia PIT-38: 30 kwietnia 2026 (art. 45 ust. 1 ustawy o PIT).

### Czy muszę coś robić jeśli PIT-8C pokazuje stratę?

Tak. Złóż PIT-38 nawet jeśli wynik to strata. Stratę ze sprzedaży papierów wartościowych możesz odliczyć od dochodu z tego samego źródła w ciągu kolejnych 5 lat (art. 9 ust. 3 ustawy o PIT). Żeby skorzystać z tego odliczenia w przyszłości, strata musi być wykazana w deklaracji za rok jej poniesienia.

### Czy PIT-8C z mBank eMakler obejmuje dywidendy?

Dywidendy z polskich spółek (GPW) mogą być uwzględnione w PIT-8C lub rozliczone przez spółkę wypłacającą jako płatnika. Sprawdź, czy Twój PIT-8C zawiera sekcję dotyczącą dywidend. [Dywidendy z zagranicznych akcji lub ETF](/blog/rozliczenie-dywidend-zagranicznych) **nie są** objęte PIT-8C — musisz je samodzielnie wykazać w PIT-38 z załącznikiem PIT/ZG (osobny dla każdego kraju źródła). [DO LEGAL REVIEW: potwierdzenie zasad dotyczących dywidend krajowych i zagranicznych przez mBank eMakler — zakres obowiązku płatnika]

### Kupiłem ETF przez mBank eMakler, ale nie sprzedałem go w 2025 roku. Czy muszę to wykazywać?

Nie. Samo posiadanie papierów wartościowych nie jest zdarzeniem podatkowym. Obowiązek wykazania w PIT-38 powstaje dopiero przy sprzedaży (zamknięciu pozycji). Jeśli w 2025 roku kupiłeś ETF, ale go nie sprzedałeś — nie wykazujesz tej transakcji.

---

Artykuł dotyczy roku podatkowego 2025 (PIT-38 składany do 30 kwietnia 2026).
Ostatnia weryfikacja merytoryczna: 2026-04-04.
Przepisy podatkowe mogą ulec zmianie — przed złożeniem deklaracji sprawdź aktualne przepisy.

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego w rozumieniu
ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym. W przypadku wątpliwości skonsultuj
się z licencjonowanym doradcą podatkowym.*

<!-- QUALITY: Q2-DRAFT | placeholdery: 1 (DO LEGAL REVIEW: zakres PIT-8C dla transakcji zagranicznych eMakler) | kursy NBP uzupełnione (EUR 09.01.2025: 4.2794, EUR 04.09.2025: 4.2553), DO WERYFIKACJI adapter rozwiązany (brak adaptera — ręczne wprowadzenie) | data: 2026-04-14 -->
