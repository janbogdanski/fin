---
title: "Kalkulator podatku giełdowego — porównanie narzędzi (2027)"
slug: kalkulator-podatku-gieldowego-porownanie
description: "Porównanie narzędzi do rozliczenia podatku giełdowego: TaxPilot vs PodatekGiełdowy.pl vs rozliczenie ręczne. Cena, obsługiwani brokerzy, format eksportu, FIFO cross-broker."
date: 2026-12-15
keywords: [kalkulator podatku gieldowego, podatekgieldowy.pl opinie, porownanie narzedzi pit-38, kalkulator pit-38, rozliczenie podatku gieldowego narzedzia]
schema: Article
---

# Kalkulator podatku giełdowego — porównanie narzędzi (2027)

Rozliczenie podatku giełdowego to coraz bardziej złożone zadanie — szczególnie jeśli inwestujesz przez brokerów zagranicznych, masz transakcje w kilku walutach i musisz zastosować FIFO z przeliczeniem na PLN. Na rynku jest kilka narzędzi, które obiecują automatyzację tego procesu. Które wybrać?

W tym artykule porównuję trzy podejścia: **TaxPilot**, **PodatekGiełdowy.pl** i **rozliczenie ręczne** (Excel/arkusz kalkulacyjny). Skupiam się na faktach — cenach, funkcjach, obsługiwanych brokerach — bez subiektywnych ocen.

> **Disclaimer (stan na grudzień 2026):** Informacje w tym artykule opierają się na danych publicznie dostępnych na stronach internetowych porównywanych narzędzi w dniu publikacji. Ceny, funkcje i obsługiwani brokerzy mogą ulec zmianie. Autorzy TaxPilot dołożyli starań, aby porównanie było rzetelne i obiektywne. W razie nieścisłości prosimy o kontakt — poprawimy niezwłocznie.

## Tabela porównawcza

| Kryterium | TaxPilot | PodatekGiełdowy.pl | Rozliczenie ręczne |
|---|---|---|---|
| **Cena** | od 49 PLN/rok | od 49 PLN/rok | Bezpłatne (Twój czas) |
| **Format wyniku** | XML (e-Deklaracje) | PDF + Excel | PDF (drukujesz sam) |
| **Brokerzy zagraniczni** | IBKR, Degiro, Revolut | IBKR, Degiro, XTB | Dowolni (ręcznie) |
| **Brokerzy polscy** | Bossa PL, XTB (XLSX) | XTB PL, mBank | Dowolni (ręcznie) |
| **FIFO cross-broker** | Tak | Częściowe | Ręczne |
| **Kursy NBP** | Automatyczne | Automatyczne | Ręczne szukanie |
| **PIT/ZG (dywidendy)** | Automatyczny | Tak | Ręczne |
| **Audit trail** | Tak (szczegółowy raport) | Podstawowy | Brak |
| **Korekta deklaracji** | Tak | Tak | Ręczna |
| **Wsparcie techniczne** | Email + chat | Email | Brak |

**Uwaga:** Powyższe dane odzwierciedlają stan na dzień publikacji. Sprawdź aktualne cenniki i funkcje na stronach narzędzi.

## TaxPilot — szczegółowy opis

### Co to jest

TaxPilot to narzędzie online do automatycznego rozliczenia PIT-38 z inwestycji giełdowych, ze szczególnym naciskiem na brokerów zagranicznych. Generuje gotowy plik XML do wysłania przez e-Deklaracje.

### Obsługiwani brokerzy

TaxPilot obsługuje import danych z następujących brokerów:

- **Interactive Brokers** — Activity Statement (CSV)
- **Degiro** — Transactions oraz Account Statement (CSV, dwa warianty eksportu)
- **Revolut** — historia transakcji giełdowych (CSV)
- **Bossa** — eksport historii (CSV, kodowanie Windows-1250)
- **XTB** — wyciąg z konta (XLSX — plik Excel, nie CSV)

> **Uwaga dla użytkowników XTB:** TaxPilot importuje plik XLSX z sekcją „Closed Positions" lub „Cash Operations". Pobierz wyciąg przez panel XTB → Historia → Eksportuj do Excela.

Więcej o rozliczeniu poszczególnych brokerów:
- [Rozliczenie Interactive Brokers PIT-38](/blog/rozliczenie-interactive-brokers)
- [Rozliczenie Degiro PIT-38](/blog/rozliczenie-degiro)
- [Rozliczenie Bossa Zagranica PIT-38](/blog/rozliczenie-bossa)

### Format wyniku

TaxPilot generuje plik XML zgodny z aktualnym schematem XSD Ministerstwa Finansów. Plik zawiera PIT-38 i (jeśli potrzebny) załącznik PIT/ZG. Gotowy do wysłania przez bramkę e-Deklaracji.

Więcej o formacie XML: [PIT-38 XML — jak wysłać przez e-Deklaracje](/blog/pit-38-xml-e-deklaracje)

### FIFO cross-broker

TaxPilot stosuje FIFO globalnie, uwzględniając transakcje ze wszystkich wgranych brokerów. Jeśli masz Apple kupione przez IBKR i sprzedane przez Bossa — FIFO zostanie zastosowane poprawnie.

### Audit trail

Po wygenerowaniu rozliczenia TaxPilot udostępnia szczegółowy raport, który pokazuje:

- Każdą transakcję sprzedaży z przypisanymi kupnami (wg FIFO)
- Kurs NBP użyty do przeliczenia
- Obliczenie przychodu i kosztu w PLN
- Dywidendy z rozbiciem na kraje i stawki WHT

Raport służy jako dokumentacja w razie kontroli urzędu skarbowego. Wiesz dokładnie, skąd wzięła się każda kwota w PIT-38.

### Cena

Od 49 PLN za rozliczenie jednego roku podatkowego. Aktualna cena na [taxpilot.pl](https://taxpilot.pl).

## PodatekGiełdowy.pl — szczegółowy opis

### Co to jest

PodatekGiełdowy.pl to jedno z najdłużej działających narzędzi do rozliczenia podatku giełdowego w Polsce. Oferuje kalkulator online, który na podstawie wgranych danych transakcyjnych oblicza podatek.

### Obsługiwani brokerzy

Według informacji na stronie, PodatekGiełdowy.pl obsługuje import z:

- **Interactive Brokers** — Activity Statement
- **Degiro** — raport transakcji
- **XTB** — historia transakcji
- Polskie domy maklerskie (mBank, XTB PL i inne)

Lista obsługiwanych brokerów może się różnić w zależności od planu cenowego.

### Format wyniku

PodatekGiełdowy.pl generuje rozliczenie w formacie PDF i Excel. PDF zawiera gotowy formularz PIT-38 do wydrukowania lub przepisania danych. Nie generuje pliku XML do e-Deklaracji — dane z PDF trzeba ręcznie wprowadzić do bramki e-Deklaracji lub interaktywnego formularza.

### FIFO cross-broker

PodatekGiełdowy.pl obsługuje FIFO, ale zakres obsługi cross-broker (łączenie transakcji z wielu brokerów w jednym rozliczeniu FIFO) może zależeć od planu cenowego i wersji narzędzia. Warto zweryfikować na stronie.

### Audit trail

Narzędzie udostępnia podstawowe zestawienie transakcji. Szczegółowość raportu (przypisanie kupno-sprzedaż, kursy NBP) zależy od wersji.

### Cena

Od 49 PLN za rozliczenie jednego roku podatkowego. Aktualna cena na [podatekgieldowy.pl](https://podatekgieldowy.pl).

## Rozliczenie ręczne — szczegółowy opis

### Co to jest

Rozliczenie ręczne oznacza samodzielne obliczenie podatku w arkuszu kalkulacyjnym (Excel, Google Sheets, LibreOffice Calc) na podstawie danych eksportowanych z brokerów.

### Obsługiwani "brokerzy"

Dowolni. Ręczne rozliczenie nie jest ograniczone formatem importu — dane wpisujesz samodzielnie. To jednocześnie zaleta (elastyczność) i wada (ręczna praca).

### Format wyniku

Arkusz kalkulacyjny z Twoimi obliczeniami. Na jego podstawie wypełniasz PIT-38 ręcznie — przez interaktywny PDF, Twój e-PIT lub papierowo.

### FIFO cross-broker

Możliwe, ale wymaga ręcznego sortowania wszystkich transakcji kupna ze wszystkich brokerów chronologicznie i ręcznego przypisywania do sprzedaży. Przy kilkudziesięciu transakcjach to żmudna praca. Przy kilkuset — realne ryzyko błędu.

### Audit trail

Twój arkusz jest jednocześnie dokumentacją. Jakość zależy od tego, jak starannie go prowadzisz.

### Cena

Bezpłatne — płacisz swoim czasem. Przy prostym portfelu (kilka transakcji, jeden broker) to rozsądna opcja. Przy złożonym (wielu brokerów, setki transakcji, dywidendy) — czas liczony w godzinach lub dniach.

## Porównanie szczegółowe: kluczowe kryteria

### Kryterium 1: Format eksportu (XML vs PDF)

To fundamentalna różnica. **XML** wysyłasz bezpośrednio przez bramkę e-Deklaracji — kliknij, wyślij, gotowe. **PDF** musisz albo wydrukować i zanieść do urzędu, albo ręcznie przepisać dane do Twój e-PIT lub interaktywnego formularza.

Przy złożonym rozliczeniu (wiele transakcji, PIT/ZG) ręczne przepisywanie danych z PDF to dodatkowe ryzyko literówki.

| Narzędzie | XML (e-Deklaracje) | PDF | Excel |
|---|---|---|---|
| TaxPilot | Tak | Tak | Nie |
| PodatekGiełdowy.pl | Nie | Tak | Tak |
| Ręcznie | Nie | Nie | Tak |

### Kryterium 2: FIFO cross-broker

FIFO (First In, First Out) to metoda rozliczania, którą narzuca polskie prawo podatkowe. Jeśli masz ten sam instrument u kilku brokerów, FIFO stosuje się **globalnie** — chronologicznie, niezależnie od brokera.

Przykład: Kupujesz 10 akcji Apple przez IBKR w styczniu, 10 akcji Apple przez Bossa w marcu. Sprzedajesz 15 akcji Apple przez Bossa w sierpniu. Wg FIFO: 10 szt. z IBKR (styczeń) + 5 szt. z Bossa (marzec).

Narzędzie, które nie łączy transakcji cross-broker, wyliczy FIFO błędnie.

| Narzędzie | FIFO per broker | FIFO cross-broker |
|---|---|---|
| TaxPilot | Tak | Tak |
| PodatekGiełdowy.pl | Tak | Częściowe (zależy od wersji) |
| Ręcznie | Możliwe (ręcznie) | Możliwe (ręcznie, trudne) |

### Kryterium 3: Audit trail

Audit trail to dokumentacja, która pokazuje, jak obliczono każdą kwotę w PIT-38. W razie kontroli urzędu skarbowego — to Twoje zabezpieczenie. Musisz umieć wyjaśnić, skąd wzięła się każda liczba.

Dobry audit trail zawiera:
- Powiązanie sprzedaż → kupno (FIFO)
- Kurs NBP z konkretnego dnia
- Obliczenie przychodu i kosztu w PLN
- Prowizje przypisane do transakcji

| Narzędzie | Audit trail |
|---|---|
| TaxPilot | Szczegółowy raport z powiązaniem transakcji |
| PodatekGiełdowy.pl | Podstawowe zestawienie |
| Ręcznie | Zależy od Ciebie |

### Kryterium 4: Czas potrzebny na rozliczenie

To kryterium subiektywne i zależy od złożoności portfela. Orientacyjne szacunki:

| Scenariusz | TaxPilot | PodatekGiełdowy.pl | Ręcznie |
|---|---|---|---|
| 1 broker, 10 transakcji | 5 min | 10 min | 1-2 h |
| 2 brokerów, 50 transakcji | 10 min | 15-20 min | 4-8 h |
| 3+ brokerów, 200+ transakcji | 15 min | 30+ min | 2-3 dni |
| + dywidendy zagraniczne | +2 min | +5-10 min | +kilka h |

Czasy dla narzędzi automatycznych obejmują: import pliku transakcji (CSV lub XLSX zależnie od brokera), weryfikację danych, wygenerowanie rozliczenia. Czas dla rozliczenia ręcznego obejmuje: eksport danych, szukanie kursów NBP, obliczenia FIFO, wypełnienie formularza.

### Kryterium 5: Cena vs wartość

| Podejście | Cena | Co dostajesz |
|---|---|---|
| TaxPilot | od 49 PLN | XML + PDF + audit trail + FIFO cross-broker |
| PodatekGiełdowy.pl | od 49 PLN | PDF + Excel + FIFO |
| Ręcznie | 0 PLN + Twój czas | Arkusz (jakość zależy od Ciebie) |

Przy 10 transakcjach rocznie rozliczenie ręczne może mieć sens — szczególnie jeśli inwestujesz przez jednego brokera w jednej walucie. Przy złożonym portfelu (wielu brokerów, wiele walut, dywidendy) — koszt narzędzia automatycznego zwraca się w zaoszczędzonym czasie.

## Kiedy wybrać które rozwiązanie?

### Rozliczenie ręczne ma sens, gdy:

- Masz jednego brokera polskiego (z PIT-8C)
- Kilka transakcji rocznie (do 10-15)
- Jedna waluta
- Brak dywidend zagranicznych
- Lubisz mieć pełną kontrolę nad obliczeniami

### PodatekGiełdowy.pl sprawdzi się, gdy:

- Masz jednego lub dwóch brokerów
- Kilkadziesiąt transakcji rocznie
- PDF jako format wyniku Ci wystarczy
- Nie potrzebujesz XML do e-Deklaracji

### TaxPilot sprawdzi się, gdy:

- Masz wielu brokerów (w tym zagranicznych)
- Setki transakcji rocznie
- Potrzebujesz XML do e-Deklaracji
- FIFO cross-broker jest istotne
- Chcesz szczegółowy audit trail
- Masz dywidendy z wielu krajów

## Na co zwrócić uwagę przy wyborze

Niezależnie od wybranego narzędzia, sprawdź:

1. **Czy obsługuje Twojego brokera** — nie wszystkie narzędzia importują dane ze wszystkich brokerów. Sprawdź listę obsługiwanych formatów.

2. **Czy stosuje FIFO poprawnie** — FIFO w PLN (z przeliczeniem po kursach NBP) to nie to samo co FIFO w walucie transakcji. Upewnij się, że narzędzie przelicza najpierw, a potem stosuje FIFO.

3. **Czy generuje PIT/ZG** — jeśli masz dywidendy zagraniczne, potrzebujesz załącznika PIT/ZG. Nie wszystkie narzędzia go generują automatycznie.

4. **Czy aktualizuje schematy** — formularze podatkowe zmieniają się co rok. Narzędzie musi używać aktualnego schematu.

5. **Czy daje audit trail** — w razie kontroli musisz umieć wyjaśnić każdą kwotę. Narzędzie z dobrym raportem oszczędza nerwów.

## Najczęstsze błędy przy wyborze narzędzia

### Błąd 1: Zaufanie do FIFO brokera

Wielu inwestorów zakłada, że kolumna "Realized P/L" w raporcie brokera (np. IBKR) to ich dochód podatkowy. To nieprawda. Broker liczy FIFO w walucie transakcji. Dla polskiego PIT-38 musisz zastosować FIFO z przeliczeniem na PLN po kursach NBP — a to daje inne wyniki, bo kurs PLN/USD zmienia się między dniem kupna a dniem sprzedaży.

### Błąd 2: Ignorowanie FIFO cross-broker

Jeśli masz Apple u dwóch brokerów i sprzedajesz u jednego — FIFO bierze najstarsze kupno **globalnie**, nie per broker. Narzędzie, które tego nie obsługuje, wyliczy błędny koszt uzyskania przychodu.

### Błąd 3: Brak PIT/ZG

Dywidendy od brokerów zagranicznych wymagają załącznika PIT/ZG. Jeśli Twoje narzędzie nie generuje PIT/ZG, musisz go wypełnić ręcznie. Brak PIT/ZG może skutkować wezwaniem z urzędu skarbowego.

### Błąd 4: Korzystanie z nieaktualnego narzędzia

Schematy formularzy podatkowych zmieniają się co rok. Plik XML wygenerowany wg zeszłorocznego schematu zostanie odrzucony. Upewnij się, że narzędzie jest zaktualizowane na aktualny sezon podatkowy.

## Inne narzędzia na rynku

Na rynku istnieją też inne rozwiązania, których nie uwzględniliśmy w szczegółowym porównaniu:

- **Pit.pl / e-pity.pl** — kompleksowe narzędzia do PIT (nie tylko giełdowego), ale z ograniczoną obsługą brokerów zagranicznych.
- **Arkusze Google / Excel od społeczności** — darmowe szablony udostępniane na forach inwestorskich. Jakość i aktualność zróżnicowana.
- **Doradca podatkowy** — profesjonalna usługa, koszt od kilkuset do kilku tysięcy PLN. Najlepsza opcja przy bardzo złożonych sytuacjach (corporate actions, opcje, futures).

## Podsumowanie

Nie ma jednego "najlepszego" narzędzia — wybór zależy od Twojej sytuacji. Prosty portfel z jednym polskim brokerem? Rozliczenie ręczne lub dowolne narzędzie. Złożony portfel z kilkoma brokerami zagranicznymi? Narzędzie z FIFO cross-broker i generowaniem XML zaoszczędzi Ci godzin pracy i zmniejszy ryzyko błędu.

Kluczowe pytania:
1. Ile mam brokerów i transakcji?
2. Czy potrzebuję XML do e-Deklaracji?
3. Czy mam ten sam instrument u wielu brokerów (FIFO cross-broker)?
4. Czy potrzebuję audit trail?

Odpowiedzi na te pytania wskażą Ci właściwe rozwiązanie.

[Sprawdź TaxPilot →](https://taxpilot.pl)

---

*Porównanie oparte na danych publicznie dostępnych na stronach narzędzi w grudniu 2026. Ceny, funkcje i obsługiwani brokerzy mogą ulec zmianie. W razie nieścisłości prosimy o kontakt na hello@taxpilot.pl.*

*Ten artykuł ma charakter informacyjny i nie stanowi doradztwa podatkowego. W przypadku wątpliwości skonsultuj się z doradcą podatkowym.*
