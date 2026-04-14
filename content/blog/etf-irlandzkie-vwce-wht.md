---
title: "ETF irlandzkie (VWCE) a podatek PIT-38 — podwójne opodatkowanie dywidend czy mit?"
slug: etf-irlandzkie-vwce-wht
description: "ETF VWCE a PIT-38 2025: dlaczego akumulujące ETF irlandzkie nie generują podatku od dywidend, kiedy płacisz 19% i czym różni się ETF akumulujący od dystrybuującego."
date: 2026-04-04
keywords: [vwce podatek polska, etf irlandzkie pit-38, vwce pit-38 2025, wht dywidendy etf, etf akumulujący podatek, vanguard vwce rozliczenie podatkowe]
schema: Article
---

# ETF irlandzkie (VWCE) a podatek PIT-38 — podwójne opodatkowanie dywidend czy mit?

VWCE (Vanguard FTSE All-World UCITS ETF Accumulating) to jeden z najpopularniejszych ETF wśród polskich inwestorów długoterminowych. Regularnie pojawia się pytanie: czy holding VWCE powoduje podwójne opodatkowanie dywidend — raz na poziomie funduszu w Irlandii, drugi raz w Polsce? Odpowiedź brzmi: **nie, dla ETF akumulujących takich jak VWCE nie ma dywidendy do wykazania w PIT-38**.

Po przeczytaniu tego artykułu będziesz wiedział: jak działa WHT (withholding tax) na poziomie funduszu irlandzkiego, dlaczego posiadacz VWCE nie otrzymuje dywidendy i nie wykazuje jej w deklaracji podatkowej, kiedy jednak płacisz 19% podatku od VWCE, oraz czym różni się VWCE od dystrybuujących wariantów ETF. Na końcu przykład liczbowy dla sprzedaży VWCE i jak TaxPilot obsługuje tę transakcję.

---

## Spis treści

1. [ETF akumulujący vs dystrybuujący — kluczowa różnica](#krok-1)
2. [WHT na poziomie funduszu — co się dzieje z dywidendami w VWCE?](#krok-2)
3. [Domicyl w Irlandii — co to oznacza dla polskiego inwestora?](#krok-3)
4. [Sprzedaż VWCE — kiedy płacisz podatek w Polsce?](#krok-4)
5. [ETF dystrybuujące — jak wygląda opodatkowanie dywidend?](#krok-5)
6. [Jak TaxPilot obsługuje transakcje VWCE?](#krok-6)
7. [Najczęstsze pytania](#faq)

---

## ETF akumulujący vs dystrybuujący — kluczowa różnica {#krok-1}

UCITS ETF dostępne na europejskich giełdach dzielą się na dwa typy według polityki dywidendowej:

| Typ | Oznaczenie w nazwie | Co dzieje się z dywidendą spółek w funduszu? |
|---|---|---|
| **Akumulujący (Acc)** | „Accumulating" lub „Acc" | Fundusz automatycznie reinwestuje dywidendy — zwiększa wartość jednostki ETF |
| **Dystrybuujący (Dist)** | „Distributing" lub „Dist" | Fundusz wypłaca dywidendę na rachunek inwestora |

VWCE to **Vanguard FTSE All-World UCITS ETF Accumulating** — ETF akumulujący. Spółki z indeksu FTSE All-World (Apple, Microsoft, Nestlé, Samsung itd.) wypłacają dywidendy do funduszu. Fundusz **nie przekazuje ich Tobie** — reinwestuje je, kupując więcej aktywów. Efektem jest wzrost wartości jednostki ETF.

**Jako posiadacz VWCE nie otrzymujesz żadnej dywidendy na rachunek. Nie ma przychodu do wykazania w PIT-38 z tytułu samego posiadania VWCE.**

---

## WHT na poziomie funduszu — co się dzieje z dywidendami w VWCE? {#krok-2}

Dywidendy od spółek trafiają do funduszu VWCE — ale zanim to nastąpi, kraj źródła dywidendy potrąca **withholding tax (WHT)**. To podatek u źródła pobierany przez kraj, w którym spółka ma siedzibę.

Przykładowo:
- Dywidenda Apple (USA): USA potrąca WHT 15% (stawka z umowy o unikaniu podwójnego opodatkowania Irlandia-USA).
- Dywidenda Nestlé (Szwajcaria): Szwajcaria potrąca WHT [DANE POTRZEBNE: aktualna stawka WHT Szwajcaria-Irlandia z umowy podatkowej — podaj przed publikacją].
- Dywidenda z niemieckich spółek: Niemcy potrącają WHT [DANE POTRZEBNE: aktualna stawka WHT Niemcy-Irlandia — podaj przed publikacją].

Fundusz VWCE zaksięgowuje dywidendy **netto** (po odliczeniu WHT). To zmniejsza nieco efektywność funduszu w porównaniu z hipotetycznym scenariuszem zerowego WHT.

### Irlandia jako domicyl — dlaczego ma znaczenie

Vanguard wybrał Irlandię jako siedzibę funduszu nieprzypadkowo. Irlandia ma umowy o unikaniu podwójnego opodatkowania z USA, co pozwala funduszowi płacić 15% WHT od dywidend z akcji US zamiast 30% (stawka domyślna dla nierezydentów USA). Fundusz domicylowany w Polsce lub innym kraju UE bez umowy USA mógłby płacić wyższy WHT.

**Dla Ciebie jako polskiego inwestora posiadającego VWCE — WHT płacony przez fundusz nie jest Twoim podatkiem. Nie wykazujesz go w PIT-38 i nie odliczasz go od czegokolwiek.**

---

## Domicyl w Irlandii — co to oznacza dla polskiego inwestora? {#krok-3}

Fundusz zarejestrowany w Irlandii (ISIN zaczynający się od IE) podlega irlandzkiemu prawu funduszy inwestycyjnych. Polska nie nakłada podatku na fundusz ani na reinwestowane dywidendy wewnątrz funduszu.

Zdarzenie podatkowe dla polskiego inwestora z VWCE powstaje wyłącznie przy:
1. **Sprzedaży jednostek VWCE** — zysk kapitałowy (art. 30b ustawy o PIT).
2. **Ewentualnym otrzymaniu dywidendy** — ale VWCE tego nie robi (ETF akumulujący).

Mit „podwójnego opodatkowania dywidend" dla VWCE wynika z mylenia dwóch poziomów:
- WHT płacony przez fundusz od dywidend spółek → dotyczy funduszu, nie Ciebie,
- Podatek od zysku przy sprzedaży ETF → dotyczy Ciebie, ale to podatek od zysku kapitałowego, nie od dywidend.

[DO LEGAL REVIEW: weryfikacja, czy polskie przepisy podatkowe (ustawa o PIT) nie przewidują szczególnej kwalifikacji przychodów z akumulujących ETF zagranicznych — np. koncepcji „fikcyjnej dywidendy" (deemed dividend) — która mogłaby generować obowiązek podatkowy przed sprzedażą jednostek]

**Posiadanie VWCE przez rok, dwa lata, dziesięć lat — samo w sobie nie generuje żadnego obowiązku podatkowego w Polsce.**

---

## Sprzedaż VWCE — kiedy płacisz podatek w Polsce? {#krok-4}

Sprzedaż jednostek VWCE to zdarzenie podatkowe. Zysk ze sprzedaży podlega **art. 30b ust. 1 ustawy o PIT** — stawka 19%, wykazywany w PIT-38.

### Obliczenie zysku ze sprzedaży VWCE

**Przychód:** liczba sprzedanych jednostek × cena sprzedaży (EUR) × kurs NBP EUR/PLN z dnia poprzedzającego sprzedaż (art. 11a ust. 1 ustawy o PIT).

**Koszt:** liczba sprzedanych jednostek × cena kupna (EUR) × kurs NBP EUR/PLN z dnia poprzedzającego kupno.

Przy wielu transakcjach kupna stosuje się FIFO (art. 24 ust. 10 ustawy o PIT) — sprzedajesz jednostki nabyte najwcześniej.

### Przykład liczbowy

**Kupno: 15.03.2023, 10 szt. VWCE, cena 93,50 EUR**
- Poprzedni dzień roboczy: 14.03.2023
- Kurs NBP EUR/PLN z 14.03.2023: **4,6909**
- Koszt nabycia: 10 × 93,50 × 4,6909 = **4.385,99 PLN**

**Kupno: 20.09.2024, 5 szt. VWCE, cena 108,20 EUR**
- Poprzedni dzień roboczy: 19.09.2024
- Kurs NBP EUR/PLN z 19.09.2024: **4,2693**
- Koszt nabycia: 5 × 108,20 × 4,2693 = **2.309,52 PLN**

**Sprzedaż: 18.06.2025, 10 szt. VWCE, cena 122,80 EUR** (FIFO: sprzedajesz najpierw 10 szt. z kupna 15.03.2023)
- Poprzedni dzień roboczy: 17.06.2025
- Kurs NBP EUR/PLN z 17.06.2025: **4,2750**
- Przychód: 10 × 122,80 × 4,2750 = **5.249,70 PLN**
- Koszt (10 szt. z kupna 15.03.2023): 4.385,99 PLN

**Dochód = 5.249,70 − 4.385,99 = 863,71 PLN**
**Podatek = 863,71 × 19% = 164,10 PLN** (zaokrąglone do pełnych złotych: 164 PLN)

Pozostałe 5 szt. z kupna 20.09.2024 — koszt do uwzględnienia przy przyszłej sprzedaży.

**Nie ma podatku od dywidend reinwestowanych przez VWCE w trakcie trzymania. Podatek płacisz tylko od zysku ze sprzedaży jednostek.**

---

## ETF dystrybuujące — jak wygląda opodatkowanie dywidend? {#krok-5}

Dla porządku: gdybyś posiadał dystrybuujący wariant ETF (np. Vanguard FTSE All-World UCITS ETF Distributing, ticker VWRL zamiast VWCE), sytuacja byłaby inna.

**VWRL (dystrybuujący):** fundusz wypłaca dywidendę na Twój rachunek, zazwyczaj kwartalnie. To jest Twój przychód podatkowy.

| Element | VWCE (akumulujący) | VWRL (dystrybuujący) |
|---|---|---|
| Dywidenda trafia do | Funduszu (reinwestuje) | Twojego rachunku |
| Przychód z dywidend do wykazania | Brak | Tak — PIT-38 + PIT/ZG |
| Podatek od dywidend w Polsce | Brak (brak przychodu) | 19% od dywidendy brutto |
| WHT od dywidendy z ETF | Może być potrącony przez brokera | Może być potrącony przez brokera |
| Podatek od zysku przy sprzedaży | 19% od zysku kapitałowego | 19% od zysku kapitałowego |

Dla VWRL: dywidendę przeliczasz na PLN po kursie NBP z dnia poprzedzającego wypłatę, wykazujesz w PIT-38 z PIT/ZG (kraj źródła: Irlandia — siedziba funduszu). Jeśli broker potrącił WHT od dywidendy — odliczasz go od podatku polskiego (nie możesz odliczyć więcej niż wynosi podatek należny).

**Jeśli nie jesteś pewien, czy Twój ETF to akumulujący czy dystrybuujący — sprawdź ticker: VWCE = akumulujący, VWRL = dystrybuujący. Dla innych ETF szukaj „Acc" lub „Dist" w pełnej nazwie.**

---

## Jak TaxPilot obsługuje transakcje VWCE? {#krok-6}

Transakcje kupna i sprzedaży VWCE to standardowe transakcje na papierach wartościowych — TaxPilot obsługuje je przez broker adaptery.

Jeśli kupiłeś VWCE przez:
- **Degiro** — wgraj plik CSV z historią transakcji Degiro do TaxPilot. TaxPilot rozpozna VWCE (ticker i ISIN), pobierze kursy NBP EUR/PLN dla każdej transakcji i zastosuje FIFO globalnie.
- **Interactive Brokers** — analogicznie, przez adapter IBKR.
- **mBank eMakler (GPW lub Xetra)** — jeśli transakcje nie są w PIT-8C, wprowadź je ręcznie w TaxPilot — import CSV z mBank eMakler nie jest aktualnie obsługiwany.
- **Trading 212** — aktualnie brak adaptera Trading 212 w TaxPilot; wprowadź transakcje ręcznie lub skorzystaj z zewnętrznej konwersji CSV.

TaxPilot rozlicza wyłącznie sprzedaż VWCE — nie generuje żadnych pozycji podatkowych z tytułu samego posiadania jednostek.

**VWCE nie generuje dywidend do rozliczenia. TaxPilot obsługuje sprzedaż VWCE przez obsługiwane adaptery brokerów bez żadnych dodatkowych kroków — po prostu importujesz CSV i dostajesz PIT-38.**

[Rozlicz VWCE i inne ETF z TaxPilot →](https://taxpilot.pl)

---

## Najczęstsze pytania {#faq}

### Czy VWCE płaci podatek od dywidend i czy ja to muszę wykazać?

VWCE (fundusz) płaci WHT od dywidend spółek w portfelu — to podatek u źródła pobierany przez kraje, w których spółki mają siedziby. Ty jako posiadacz jednostek VWCE tego nie widzisz i nie wykazujesz. W PIT-38 wykazujesz jedynie zysk ze sprzedaży jednostek VWCE, jeśli taką sprzedaż przeprowadziłeś.

### Co jeśli VWCE zmienił politykę dywidendową i zaczął wypłacać dywidendy?

VWCE pozostaje funduszem akumulującym — nie wypłaca dywidend inwestorom (stan na datę publikacji). Gdyby fundusz zmienił politykę na dystrybuującą, zmieniłby też ticker i pełną nazwę. Jeśli nie otrzymałeś żadnej wypłaty na rachunek z tytułu posiadania VWCE — nie masz dywidend do wykazania.

### Posiadam VWCE od 3 lat i nie sprzedałem żadnej jednostki. Czy muszę składać PIT-38?

Jeśli w 2025 roku nie sprzedałeś żadnych papierów wartościowych (akcji, ETF, obligacji) i nie otrzymałeś dywidend — nie masz obowiązku składania PIT-38 za 2025 rok z tytułu VWCE. Samo posiadanie nie jest zdarzeniem podatkowym. Sprawdź, czy nie masz innych transakcji podlegających PIT-38 (np. z innych instrumentów lub kont).

### Czy zysk ze sprzedaży VWCE mogę skompensować ze stratą z akcji z innego brokera?

Tak. Zyski i straty ze sprzedaży papierów wartościowych (akcje, ETF) ze wszystkich brokerów sumuje się w PIT-38. Strata z jednego brokera kompensuje zysk z innego. Zasada ta wynika z art. 9 ust. 2 ustawy o PIT — dochód z danego źródła oblicza się jako suma przychodów minus suma kosztów z całego roku.

### Czy dywidenda z ETF dystrybuującego (VWRL) podlega podwójnemu opodatkowaniu?

W pewnym sensie tak: fundusz płaci WHT od dywidend spółek (np. 15% od dywidend US), a Ty w Polsce płacisz 19% od dywidendy brutto wypłaconej przez fundusz (z Irlandii). WHT zapłacony przez Ciebie lub potrącony przez brokera możesz odliczyć od podatku polskiego — ale tylko do wysokości 19% kwoty dywidendy. Efektywna stawka opodatkowania zależy od wysokości WHT potrąconego u źródła. [DO LEGAL REVIEW: potwierdzenie zasad odliczenia WHT z ETF dystrybuującego zarejestrowanego w Irlandii dla polskiego inwestora — art. 30b i umowa Polska-Irlandia]

---

Artykuł dotyczy roku podatkowego 2025 (PIT-38 składany do 30 kwietnia 2026).
Ostatnia weryfikacja merytoryczna: 2026-04-04.
Przepisy podatkowe mogą ulec zmianie — przed złożeniem deklaracji sprawdź aktualne przepisy.

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego w rozumieniu
ustawy z dnia 5 lipca 1996 r. o doradztwie podatkowym. W przypadku wątpliwości skonsultuj
się z licencjonowanym doradcą podatkowym.*

<!-- QUALITY: Q2-DRAFT | kursy NBP uzupełnione (EUR 14.03.2023: 4.6909, EUR 19.09.2024: 4.2693, EUR 17.06.2025: 4.2750); adapter mBank rozwiązany; placeholdery: 2 (stawki WHT Szwajcaria-Irlandia, Niemcy-Irlandia; 2x DO LEGAL REVIEW: fikcyjna dywidenda + WHT dystrybuujący) | data: 2026-04-14 -->
