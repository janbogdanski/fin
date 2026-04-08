# HEXAGONAL_PROCESS_TEST_PLAN.md

## Metadata

| | |
|---|---|
| Data | 2026-04-08 |
| Autor | Codex |
| Status | DRAFT FOR EXECUTION |
| Cel | Zdefiniowac katalog testow procesowych pisanych hexagonalnie, przez publiczne wejscia i porty, zamiast testowac implementacje klas |

---

## 1. Po co ten dokument

Obecne repo ma juz sporo sensownych testow domenowych i application-level, ale wciaz miesza 3 style:

1. test obiektu domenowego,
2. test jednej klasy aplikacyjnej,
3. test procesu biznesowego.

Najwieksza wartosc dla tego produktu daje styl nr 3:

- user wykonuje proces,
- system przechodzi przez publiczne wejscie,
- domena pracuje na realnych obiektach,
- zaleznosci zewnetrzne sa zastapione fake'ami/in-memory adapterami,
- asercje dotycza wyniku biznesowego i efektow na granicach, nie implementacji wewnetrznej.

To jest tutaj rozumiane jako **hexagonalny test procesowy**.

---

## 2. Definicja testu procesowego

Test procesowy w tym repo:

- zaczyna od publicznego wejscia:
  - `Handler`
  - `Application Service`
  - `Aggregate` tylko wtedy, gdy sam aggregate jest granica procesu domenowego
- uzywa realnych:
  - Value Objects
  - DTO
  - Domain Model
  - Domain Services
- stubuje/fakuje tylko porty wychodzace:
  - repozytoria
  - maile
  - platnosci
  - storage importow
  - provider kursow
- sprawdza:
  - wynik biznesowy
  - zapisany stan
  - bledy/warningi
  - efekt na granicy systemu
- nie sprawdza:
  - prywatnych metod
  - kolejnosci wywolan wewnatrz use case'a
  - szczegolow implementacyjnych, ktore mozna zmienic bez zmiany zachowania

### Dobre granice dla tego projektu

- `ImportOrchestrationService`
- `ImportToLedgerService`
- `ImportDividendService`
- `AnnualTaxCalculationService`
- `CalculateAnnualTaxHandler`
- `DeclarationService`
- `RequestMagicLinkHandler`
- `VerifyMagicLinkHandler`
- `CreateCheckoutSessionHandler`
- `HandlePaymentWebhookHandler`

### Czego NIE nazywamy tu testem procesowym

- parser adaptera brokera: to dalej jest test adaptera input/output
- kontroler Symfony: to jest integration/web test
- Doctrine repository: to jest integration/repository-contract test
- snapshot XML/PDF: to jest golden/snapshot test

---

## 3. Zasady organizacji

### 3.1. Naming

Dla nowych suite'ow preferujemy suffix `ProcessTest`, zeby odroznic je od testow klasocentrycznych.

Przyklad:

- `tests/Unit/BrokerImport/Application/ImportLifecycleProcessTest.php`
- `tests/Unit/TaxCalc/Application/CrossYearTopUpProcessTest.php`
- `tests/Unit/Declaration/Application/DeclarationExportProcessTest.php`

### 3.2. Test harness

Preferowane klocki bazowe:

- fake/in-memory adaptery z [tests/InMemory](/Users/janbogdanski/projects/skrypty/fin/tests/InMemory)
- mothers z [tests/Factory](/Users/janbogdanski/projects/skrypty/fin/tests/Factory)
- realne VO/DTO/Domain Services

Nowe fake'i dodajemy tylko wtedy, gdy pozwalaja testowac proces bez Doctrine/HTTP.

### 3.3. Styl asercji

Priorytet asercji:

1. wynik dla usera lub use case'a,
2. stan domenowy po procesie,
3. efekt na porcie wyjsciowym,
4. warning/error biznesowy.

Nie robimy asercji typu:

- "metoda X zostala wywolana 1 raz, a potem Y 1 raz"
- "service A wywolal helper B"

Wyjatek: port graniczny, np. mailer, platnosc, repo save.

---

## 4. Katalog procesow do testowania

### 4.1. P0 — krytyczne dla poprawnosci podatkowej

| ID | BC | Proces | Publiczne wejscie | Obecny stan | Co musi byc sprawdzone |
|---|---|---|---|---|---|
| HXP-001 | BrokerImport | Import pliku brokera z autodetekcja i fan-outem | `ImportOrchestrationService::import()` | CZESCIOWO | wykrycie brokera, parse, zapis, warningi, odpalenie FIFO i dywidend tylko wtedy gdy sa potrzebne |
| HXP-002 | BrokerImport + TaxCalc | Dogrywanie kolejnego roku bez pelnego reimportu | `ImportOrchestrationService::import()` | BRAK DOWODU / HIGH RISK | buy w roku N zostaje w systemie, sell w roku N+1 domyka stare loty po dograniu tylko nowego pliku |
| HXP-003 | TaxCalc | Replay pelnej historii jest idempotentny | `ImportToLedgerService::process()` | BRAK DOWODU / HIGH RISK | ponowne przeliczenie tej samej historii nie dubluje open/closed positions |
| HXP-004 | TaxCalc | FIFO matching cross-broker i cross-lot | `TaxPositionLedger` + `ImportToLedgerService::process()` | CZESCIOWO | najstarszy lot wygrywa niezaleznie od brokera, partial sell rozcina lot poprawnie |
| HXP-005 | TaxCalc | Import i rozliczenie dywidend/WHT | `ImportDividendService::process()` | CZESCIOWO | dividend + withholding tax, wiele krajow, brak WHT, filtrowanie po roku, brak crasha na danych nieakcyjnych |
| HXP-006 | TaxCalc | Roczna kalkulacja podatku z uwzglednieniem strat | `CalculateAnnualTaxHandler` lub `AnnualTaxCalculationService::calculate()` | CZESCIOWO | equity + dividends + prior losses + finalize + totals |
| HXP-007 | Declaration | Preview i export PIT-38 z gate'ami | `DeclarationService` | CZESCIOWO | no-data, payment-required, profile-incomplete, gotowy preview/export |

### 4.2. P1 — krytyczne dla integralnosci konta i platnosci

| ID | BC | Proces | Publiczne wejscie | Obecny stan | Co musi byc sprawdzone |
|---|---|---|---|---|---|
| HXP-008 | Identity | Request magic link | `RequestMagicLinkHandler` | CZESCIOWO | user istnieje lub powstaje, token ustawiony, mail wyslany, brak wycieku tokena poza mailem |
| HXP-009 | Identity | Verify magic link single-use | `VerifyMagicLinkHandler` | CZESCIOWO | poprawny token loguje, expired/invalid odpada, token po uzyciu jest uniewazniony |
| HXP-010 | Billing | Utworzenie checkout session | `CreateCheckoutSessionHandler` | CZESCIOWO | dobry tier i payload checkoutu, brak checkoutu dla przypadkow, ktore nie wymagaja platnosci |
| HXP-011 | Billing | Webhook platnosci jest idempotentny | `HandlePaymentWebhookHandler` | CZESCIOWO | successful payment aktywuje entitlement raz, duplikat webhooka nie psuje stanu |
| HXP-012 | Identity | Zastosowanie referral code | `ApplyReferralCodeHandler` | CZESCIOWO | referral mozna przypisac raz, kod nieprawidlowy nie zmienia stanu |

### 4.3. P2 — wazne, ale po domknieciu core tax flow

| ID | BC | Proces | Publiczne wejscie | Obecny stan | Co musi byc sprawdzone |
|---|---|---|---|---|---|
| HXP-013 | Identity | Anonimizacja usera | `AnonymizeUserHandler` | CZESCIOWO | PII znika, dane wymagane do retencji pozostaja zgodnie z polityka |
| HXP-014 | TaxCalc | Lifecycle strat z lat ubieglych | BRAK sensownego entry pointu aplikacyjnego | BRAK / ARCH GAP | zapis, update, delete, lock po uzyciu, brak mozliwosci zmiany po wykorzystaniu |
| HXP-015 | Declaration / Audit | Budowa audit trail czytelnego dla usera | nowy read-model/use case | BRAK | buy/sell z waluta zrodlowa, kursy NBP, koszt/przychod, formula i suma |

---

## 5. Priorytetowe scenariusze per proces

### HXP-001 Import pliku brokera

Minimalny zestaw:

1. pusty parse result nie zapisuje nic i nie odpala dalszego flow
2. import z SELL uruchamia FIFO tylko dla lat sprzedazy
3. import z DIVIDEND uruchamia tylko rozliczenie dywidend
4. jawnie wybrany adapter odrzuca niepasujacy plik sensownym bledem
5. warningi z FIFO wracaja do `ImportResult`

### HXP-002 Dogrywanie roku

Minimalny zestaw:

1. buy z 2024 jest juz zapisany, user dogrywa tylko sell z 2025, wynik trafia do 2025
2. buy z 2024 + sell z 2025 + drugi import 2025 nie dubluje zamknietych pozycji
3. sell bez historycznego buy daje warning biznesowy, nie crash

### HXP-003 Idempotentny replay historii

Minimalny zestaw:

1. ten sam komplet transakcji przeliczony drugi raz daje identyczny stan
2. replay po istniejacym ledgerze nie podwaja open positions
3. replay po istniejacych closed positions nie podwaja audit trail

### HXP-004 FIFO cross-broker/cross-lot

Minimalny zestaw:

1. najstarszy lot wygrywa niezaleznie od brokera
2. partial sell rozdziela prowizje i ilosci poprawnie
3. PLN path nie siega po provider kursow
4. filtr roku zwraca tylko closed positions dla zadanego roku podatkowego

### HXP-005 Dywidendy i WHT

Minimalny zestaw:

1. dividend + withholding tax z jednego dnia daje poprawny wynik netto/PL tax due
2. wiele krajow agreguje sie poprawnie
3. import bez ISIN albo bez WHT nie wysadza procesu
4. replay historii dywidend nie duplikuje wynikow

### HXP-006 Roczna kalkulacja podatku

Minimalny zestaw:

1. equity + dividend + prior loss daje poprawne total tax due
2. strata z lat ubieglych nie moze przekroczyc max deduction
3. po wykorzystaniu straty proces oznacza ja jako used/locked
4. wynik finalizuje snapshot i nie zostaje w stanie przejsciowym

### HXP-007 Preview/export PIT-38

Minimalny zestaw:

1. brak danych zwraca `NoData`
2. brak platnosci przy tierze platnym zwraca `PaymentRequired`
3. brak NIP/profilu zwraca `ProfileIncomplete`
4. komplet danych zwraca `PIT38WithSummary`

### HXP-008/HXP-009 Magic link

Minimalny zestaw:

1. request ustawia token i wysyla mail
2. verify poprawnego tokena uwierzytelnia usera i uniewaznia token
3. expired token odpada
4. ponowne uzycie tego samego tokena odpada

### HXP-010/HXP-011 Billing

Minimalny zestaw:

1. checkout session tworzy sie dla wymaganego tieru
2. webhook sukcesu aktywuje platnosc
3. webhook zduplikowany jest idempotentny
4. nieznany event nie psuje stanu

### HXP-014 Lifecycle strat

Ten proces nie ma jeszcze dobrej granicy hexagonalnej. Zanim dodamy testy procesowe, trzeba wydzielic use case aplikacyjny zamiast trzymac logike w kontrolerach.

---

## 6. Co juz mamy, a czego brakuje

### Relatywnie dobre fundamenty

- FIFO aggregate scenarios w [TaxPositionLedgerTest.php](/Users/janbogdanski/projects/skrypty/fin/tests/Unit/TaxCalc/Domain/TaxPositionLedgerTest.php)
- orchestration importu w [ImportOrchestrationServiceTest.php](/Users/janbogdanski/projects/skrypty/fin/tests/Unit/BrokerImport/ImportOrchestrationServiceTest.php)
- declaration gates w [DeclarationServiceTest.php](/Users/janbogdanski/projects/skrypty/fin/tests/Unit/Declaration/Application/DeclarationServiceTest.php)
- magic link verification w [VerifyMagicLinkHandlerTest.php](/Users/janbogdanski/projects/skrypty/fin/tests/Unit/Identity/Application/VerifyMagicLinkHandlerTest.php)

### Najwieksze luki

- brak dowodu, ze dogrywanie roku dziala poprawnie
- brak dowodu, ze replay historii jest idempotentny
- brak procesu dla lifecycle strat z lat ubieglych na sensownej granicy aplikacyjnej
- brak procesu audit trail/explainability dla usera

---

## 7. Plan dodania suite'ow

### Phase 1 — correctness core

Cel: domknac procesy, ktore moga zle policzyc podatek mimo zielonych testow klasowych.

Do dodania:

1. `ImportLifecycleProcessTest`
2. `CrossYearTopUpProcessTest`
3. `LedgerReplayIdempotencyProcessTest`
4. `DividendImportProcessTest`
5. `AnnualTaxCalculationProcessTest`
6. `DeclarationExportProcessTest`

Definition of done:

- kazdy suite ma 3-5 scenariuszy biznesowych
- testy przechodza bez Doctrine i bez HTTP
- granice procesu sa oparte o porty/fake'i, nie mocki wszystkiego

### Phase 2 — account and payment integrity

Do dodania:

7. `RequestMagicLinkProcessTest`
8. `VerifyMagicLinkProcessTest`
9. `CheckoutSessionProcessTest`
10. `PaymentWebhookProcessTest`
11. `ReferralApplicationProcessTest`

Definition of done:

- wszystkie flow sa single-purpose i idempotentne tam, gdzie powinny
- kluczowe side effecty na portach sa jawnie sprawdzone

### Phase 3 — architecture debt before tests

Do wydzielenia przed testami:

12. use case dla lifecycle `prior_year_losses`
13. use case/read model dla user-facing audit trail

Po wydzieleniu:

14. `PriorYearLossLifecycleProcessTest`
15. `AuditTrailExplanationProcessTest`

---

## 8. Proponowana kolejnosc wdrazania

1. HXP-002 dogrywanie roku
2. HXP-003 idempotentny replay
3. HXP-006 roczna kalkulacja + straty
4. HXP-007 declaration gates
5. HXP-005 dywidendy replay/idempotency
6. HXP-009 magic link single-use
7. HXP-011 payment webhook idempotency

To jest najlepsza kolejnosc, bo najpierw zamyka ryzyko blednego wyniku podatkowego, a dopiero potem integralnosc konta i billing.

---

## 9. Kryteria review dla kazdego nowego suite'u

Reviewer odrzuca suite, jezeli:

- testuje prywatne metody albo helpery
- mockuje wiecej rzeczy niz trzeba
- asercje dotycza call order zamiast wyniku biznesowego
- scenariusz nie jest opisany jezykiem domeny
- nie wiadomo, jakie ryzyko biznesowe dany test chroni

Reviewer akceptuje suite, jezeli:

- nazwa testu odpowiada procesowi usera lub use case'owi
- wejscie jest publiczne
- wynik jest czytelny dla biznesu i developera
- fake porty upraszczaja test zamiast udawac infrastrukture 1:1

---

## 10. Decyzja

Docelowo nie chcemy zastapic obecnych unit testow klasowych w 100%.

Chcemy przesunac srodek ciezkosci:

- mniej testow "ta klasa wywoluje tamta klase"
- wiecej testow "ten proces daje taki wynik biznesowy"

To jest lepiej dopasowane do architektury hexagonalnej, DDD i do ryzyka tego produktu.
