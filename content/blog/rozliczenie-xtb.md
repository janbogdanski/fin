---
title: "XTB a podatek giełdowy PIT-38 — kompletny poradnik (2027)"
slug: rozliczenie-xtb
description: "Jak rozliczyć inwestycje z XTB w PIT-38? XTB wydaje PIT-8C — ale nie zawsze wystarczy. Kiedy potrzebujesz ręcznego rozliczenia i jak zaimportować wyciąg XTB do TaxPilot."
date: 2026-12-20
keywords: [xtb pit-38, xtb podatek gieldowy, xtb pit-8c, rozliczenie xtb akcje, xtb deklaracja podatkowa, xtb pit-38 2027]
schema: Article
---

# XTB a podatek giełdowy PIT-38 — kompletny poradnik (2027)

XTB to jeden z największych polskich brokerów — miliony klientów w Polsce inwestuje przez ich platformę w akcje, ETF-y i CFD. Dobra wiadomość: **XTB jest polskim biurem maklerskim i wydaje PIT-8C**. Zła wiadomość: PIT-8C od XTB nie zawsze jest kompletnym rozwiązaniem. W tym artykule wyjaśniam, kiedy PIT-8C wystarczy, a kiedy potrzebujesz pełnego rozliczenia — i jak TaxPilot może w tym pomóc.

> Ten artykuł dotyczy wyłącznie rachunków maklerskich w akcjach i ETF-ach (tzw. xStation Real Stocks). Jeśli handlujesz CFD na XTB — nie składasz PIT-38, lecz PIT-36L lub inne. CFD są innym instrumentem, który nie jest przedmiotem tego artykułu.

## Spis treści

1. [XTB i PIT-8C — co to oznacza dla Ciebie?](#pit-8c)
2. [Kiedy PIT-8C od XTB wystarczy](#kiedy-wystarcza)
3. [Kiedy PIT-8C od XTB nie wystarczy](#kiedy-nie-wystarcza)
4. [Jak wyeksportować wyciąg z XTB](#eksport)
5. [Import do TaxPilot](#import)
6. [FIFO cross-broker — kluczowy scenariusz](#fifo)
7. [Dywidendy z XTB](#dywidendy)
8. [Kursy NBP — jak XTB je stosuje?](#kursy)
9. [Najczęstsze błędy przy rozliczaniu XTB](#bledy)

---

## XTB i PIT-8C — co to oznacza dla Ciebie? {#pit-8c}

PIT-8C to informacja podatkowa, którą **polskie biuro maklerskie** ma obowiązek wystawić każdemu klientowi, który realizował transakcje. XTB jako firma zarejestrowana w Polsce (X-Trade Brokers Dom Maklerski S.A., nadzorowana przez KNF) **wydaje PIT-8C do końca lutego** za poprzedni rok podatkowy.

PIT-8C zawiera:
- Łączny przychód z transakcji (sekcja D lub E)
- Łączny koszt uzyskania przychodu (wg. metodologii XTB)
- Dochód lub stratę

Dane z PIT-8C przepisujesz do PIT-38, pozycje P_20 do P_22 (lub analogiczne dla 2025). Brzmi prosto. I jest proste — ale tylko w określonych sytuacjach.

---

## Kiedy PIT-8C od XTB wystarczy {#kiedy-wystarcza}

PIT-8C od XTB jest kompletnym dokumentem podatkowym, gdy:

- **Masz wyłącznie rachunek w XTB** — nie inwestujesz jednocześnie przez IBKR, Degiro, Bossa ani żadnego innego brokera
- **Nie masz strat do przeniesienia z poprzednich lat** — lub takie straty zostały już uwzględnione przez XTB w PIT-8C
- **Nie masz dywidend z zagranicznych spółek** wymagających rozliczenia WHT

W takim przypadku: wejdź na swoje konto XTB → Dokumenty → pobierz PIT-8C → przepisz dane do e-Deklaracji lub Twój e-PIT.

> **Uwaga:** Zawsze sprawdź, czy wartości w PIT-8C są zgodne z Twoimi własnymi transakcjami. Każde biuro maklerskie może stosować własne zaokrąglenia i metodologię FIFO — warto zweryfikować przynajmniej przy większych kwotach.

---

## Kiedy PIT-8C od XTB nie wystarczy {#kiedy-nie-wystarcza}

### Scenariusz 1: Inwestujesz przez kilka brokerów jednocześnie

Jeśli kupujesz Apple przez IBKR i sprzedajesz przez XTB (lub odwrotnie), **FIFO musi być stosowane globalnie**. XTB wie tylko o transakcjach na swoim rachunku. Jeśli kupowałeś akcje Apple wcześniej przez innego brokera, XTB nie uwzględni ich w swoim PIT-8C.

Przykład:
- Styczeń 2024: kupujesz 10 akcji Apple przez IBKR po 180 USD
- Marzec 2025: kupujesz 10 akcji Apple przez XTB po 200 USD
- Czerwiec 2025: sprzedajesz 15 akcji Apple przez XTB po 210 USD

XTB wykaże w PIT-8C koszt dla 15 akcji licząc od transakcji XTB z marca 2025. Ale wg. polskiego prawa podatkowego FIFO działa globalnie — 10 szt. z IBKR (styczeń 2024) + 5 szt. z XTB (marzec 2025). Koszt będzie inny, podatek będzie inny.

**W tym scenariuszu PIT-8C od XTB jest nieprawidłowe.** Musisz przeliczyć wszystko samodzielnie lub użyć narzędzia obsługującego cross-broker FIFO.

### Scenariusz 2: Dywidendy z zagranicznych spółek

Dywidendy od zagranicznych spółek wypłacane przez XTB są objęte podatkiem u źródła (WHT). Stawka zależy od umów o unikaniu podwójnego opodatkowania (UPO). XTB może uwzględnić WHT w PIT-8C — ale sposób, w jaki to robi, warto sprawdzić samodzielnie, szczególnie przy spółkach z krajów z korzystnymi UPO (np. USA: 15% zamiast 19%).

### Scenariusz 3: Straty z poprzednich lat do odliczenia

Jeśli poniosłeś stratę z akcji w 2022, 2023 lub 2024 — masz prawo odliczać ją przez kolejne 5 lat (maksymalnie 50% straty rocznie). XTB nie wie o stratach z innych brokerów i nie ma obowiązku uwzględniać ich w swoim PIT-8C. To Twoje zadanie.

---

## Jak wyeksportować wyciąg z XTB {#eksport}

TaxPilot importuje wyciąg XTB w formacie **XLSX (Excel)** — nie CSV. Eksport znajdziesz w panelu XTB:

1. Zaloguj się na [xtb.com](https://xtb.com) → Platforma xStation → Historia
2. Wybierz sekcję **Historia transakcji** lub **Zamknięte pozycje**
3. Ustaw zakres dat: cały rok podatkowy (1.01–31.12.2025)
4. Eksportuj jako **Excel (.xlsx)**

> **Wskazówka:** W nazwie pliku podaj kod waluty rachunku — np. `XTB_USD_2025.xlsx`. TaxPilot odczytuje walutę konta z nazwy pliku i używa jej do przeliczenia transakcji. Wymagany format: kod waluty musi być otoczony podkreślnikami (np. `_USD_`) — plik `XTB_USD.xlsx` (bez podkreślnika po walucie) nie zostanie rozpoznany.

Plik XLSX powinien zawierać arkusze:
- **Closed Positions** — historia zamkniętych pozycji (akcje kupione i sprzedane)
- **Cash Operations** — dywidendy, podatki u źródła, inne operacje gotówkowe

TaxPilot parsuje transakcje handlowe (kupno/sprzedaż) z **Cash Operations**, jeśli arkusz zawiera wpisy `stock purchase` lub `stock sell`. Jeśli takich wpisów brak, transakcje pobierane są z **Closed Positions**. Dywidendy i podatki u źródła (WHT) z Cash Operations są importowane niezależnie — zawsze, gdy arkusz jest obecny.

---

## Import do TaxPilot {#import}

1. Zaloguj się na [taxpilot.pl](https://taxpilot.pl)
2. Przejdź do **Import** → Prześlij plik
3. Wybierz plik XLSX z wyciągiem XTB
4. TaxPilot automatycznie rozpozna format XTB i przetworzy transakcje

### Co TaxPilot importuje z XTB

| Dane | Dostępność |
|---|---|
| Instrument / ticker | Tak |
| Typ transakcji (kupno / sprzedaż) | Tak |
| Data otwarcia i zamknięcia pozycji | Tak |
| Cena otwarcia i zamknięcia | Tak |
| Wolumen (liczba akcji) | Tak |
| Waluta rachunku | Z nazwy pliku (wymagane) |
| Prowizja | ⚠ Brak — XTB nie eksportuje prowizji w wyciągu; TaxPilot przyjmuje 0 PLN prowizji |
| ISIN | ⚠ Brak — XTB eksportuje ticker, nie ISIN; cross-broker FIFO działa na symbolach |

### Ograniczenia importu XTB

- **Zero prowizji w importie.** XTB pobiera prowizje, ale nie są one widoczne w standardowym wyciągu XLSX. Jeśli chcesz uwzględnić prowizje w kosztach, musisz je dodać ręcznie lub sprawdzić w sekcji Cash Operations.
- **Brak ISIN.** Jeśli ten sam instrument handlujesz przez XTB i inny broker (np. IBKR), cross-broker FIFO działa na podstawie symbolu/tickera. Jeśli tickery się różnią (np. `AAPL` vs `AAPL.US`), FIFO może nie działać poprawnie — sprawdź i ewentualnie ujednolic.

---

## FIFO cross-broker — kluczowy scenariusz {#fifo}

Główna wartość TaxPilot dla użytkowników XTB pojawia się wtedy, gdy inwestujesz przez kilka brokerów jednocześnie. Wgraj wyciągi z **wszystkich** brokerów — TaxPilot obliczy FIFO globalnie, uwzględniając chronologię transakcji niezależnie od tego, przez którego brokera były zrealizowane.

Przykład dwubrokowy (XTB + IBKR):
- Wgraj plik XLSX z XTB + Activity Statement z IBKR
- TaxPilot połączy transakcje, posortuje chronologicznie i zastosuje FIFO globalnie
- Wynik: poprawny PIT-38 uwzględniający obie platformy

---

## Dywidendy z XTB {#dywidendy}

Jeśli w sekcji **Cash Operations** wyciągu XTB znajdą się wpisy:
- `dividend` → TaxPilot importuje jako dywidendę brutto (art. 30a PIT)
- `withholding tax` → TaxPilot importuje jako WHT (podatek pobrany u źródła)

TaxPilot automatycznie obliczy, ile dodatkowego podatku w Polsce jest do zapłaty po uwzględnieniu UPO (dla USA zazwyczaj: 19% - 15% WHT = 4% dopłaty; dla krajów bez UPO: pełne 19%).

---

## Kursy NBP — jak XTB je stosuje? {#kursy}

XTB eksportuje transakcje **w walucie rachunku** (np. USD lub EUR), bez przeliczenia na PLN. TaxPilot pobiera kursy NBP automatycznie:

- Kurs z **ostatniego dnia roboczego przed datą transakcji** (wymóg art. 11a ust. 2 ustawy o PIT)
- Kursy pobierane z API NBP — historyczne tabele A

Jeśli data transakcji wypada w weekend lub święto — TaxPilot automatycznie znajdzie właściwy kurs z poprzedniego dnia roboczego.

> **Uwaga:** XTB w swoim PIT-8C stosuje własną metodologię kursu NBP. Jeśli porównujesz wyniki TaxPilot z wartościami z PIT-8C — drobne różnice wynikające z zaokrągleń kursów są możliwe i normalne. Istotne jest, że TaxPilot stosuje metodologię zgodną z przepisami.

---

## Najczęstsze błędy przy rozliczaniu XTB {#bledy}

### 1. Przepisanie PIT-8C bez weryfikacji przy kilku brokerach

Najpoważniejszy błąd: masz konto w XTB i IBKR, przepisujesz PIT-8C od XTB i myślisz, że masz pełne rozliczenie. Nie masz. IBKR nie ma obowiązku wysyłać PIT-8C — to Twoje zadanie połączyć dane z obu brokerów.

### 2. Brak uwzględnienia strat z poprzednich lat

Jeśli straciłeś na giełdzie w 2022 lub 2023 — pamiętaj o prawie do odliczenia straty. XTB nie robi tego za Ciebie automatycznie. W TaxPilot możesz dodać straty z poprzednich lat ręcznie w sekcji "Straty z lat ubiegłych".

### 3. Eksport CSV zamiast XLSX

TaxPilot wymaga pliku **XLSX** z XTB, a nie CSV. Jeśli eksportujesz w innym formacie, import nie zadziała. Sprawdź format przed wgraniem.

### 4. Brak kodu waluty w nazwie pliku

TaxPilot odczytuje walutę rachunku z nazwy pliku. Jeśli plik nie zawiera kodu waluty w wymaganym formacie (np. `wyciag.xlsx`), waluta nie zostanie wykryta i import zakończy się błędami przy przetwarzaniu poszczególnych transakcji. Nazwij plik np. `XTB_USD_2025.xlsx` (podkreślnik po kodzie waluty jest wymagany).

### 5. Ignorowanie dywidend z zagranicznych ETF-ów

VWCE, CSPX i podobne ETF-y wypłacają dywidendy lub dokonują ich akumulacji. Dla ETF-ów akumulacyjnych (Acc) nie ma dywidend do rozliczenia. Dla dystrybucyjnych (Dist) — tak. Sprawdź typ ETF przed rozliczeniem.

---

## Podsumowanie: XTB + TaxPilot

| Scenariusz | Czy potrzebujesz TaxPilot? |
|---|---|
| Wyłącznie XTB, akcje polskie, brak innych brokerów | Nie — PIT-8C wystarczy |
| Wyłącznie XTB, akcje zagraniczne, brak innych brokerów | Opcjonalnie — do weryfikacji i generowania XML |
| XTB + inny broker (IBKR, Degiro, Revolut, Bossa) | **Tak** — cross-broker FIFO wymagany |
| XTB z dywidendami z zagranicznych spółek | **Tak** — WHT i UPO calculation |
| XTB ze stratami z poprzednich lat do odliczenia | **Tak** — do prawidłowego odliczenia |

---

> **Disclaimer:** Ten artykuł ma charakter informacyjny i edukacyjny. Nie stanowi porady podatkowej ani prawnej. Zasady opodatkowania mogą ulec zmianie. W przypadku wątpliwości skonsultuj się z doradcą podatkowym. Autorzy dołożyli starań, aby informacje były aktualne i zgodne z przepisami na dzień publikacji.
