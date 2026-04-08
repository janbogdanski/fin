# TEST_METRICS.md — TaxPilot Test Strategy Refinement

## Metadata

| | |
|---|---|
| Data | 2026-04-03 |
| Autorzy | Tech Lead (decyzja), QA Lead (Kasia), Security Auditor (Michał P.), Tax Advisor (Tomasz), Performance Engineer (Aleksandra), Senior Dev (Marek), Legal Reviewer (Mec. Wiśniewska) |
| Kontekst | FinTech, PIT-38, dane osobowe (NIP), B2C, public beta luty 2027 |
| Input | docs/TEST_STRATEGY.md, docs/ARCHITECTURE.md, phpunit.xml.dist, infection.json5, .github/workflows/ci.yml, deptrac.yaml, BACKLOG.md |
| Status | ZATWIERDZONE |

---

## 1. Tabela metryk

### 1.1. Metryki testowe per poziom

| # | Poziom testu | Metryka | Wartość obecna | Target Beta (02.2027) | Target Production (po sezonie) | Uzasadnienie |
|---|---|---|---|---|---|---|
| 1 | **Unit** | Liczba testów | 573 | >= 750 | >= 1000 | Naturalny przyrost z nowymi features. Targetujemy coverage, nie liczbę. |
| 2 | **Unit** | Line coverage (Domain+App) | ~85% (est.) | >= 90% | >= 92% | CODING_STANDARDS rule 16 wymaga 90%. Domain to core — błąd = błędny podatek. >95% = diminishing returns. |
| 3 | **Unit** | Line coverage (Infrastructure) | ~60% (est.) | >= 65% | >= 70% | Kontrolery, Doctrine repo — niska testowalność unit, lepiej pokryć integration. |
| 4 | **Integration** | Liczba testów | 65 | >= 90 | >= 120 | Każdy endpoint, każdy adapter, każdy repository contract. |
| 5 | **Golden Dataset** | Liczba scenariuszy | 11 | >= 20 | >= 30 | Pokrycie reguł podatkowych wg analizy Tomasza. Każdy sign-off przez doradcę. |
| 6 | **Golden Dataset** | Tax rule coverage | ~55% | >= 85% | >= 95% | % reguł podatkowych (z listy Tomasza) pokrytych golden dataset. |
| 7 | **Property-based** | Liczba testów | 4 | >= 12 | >= 15 | FIFO: 5 properties. Money: 3 properties. TaxCalc: 4 properties. Underinvested dziś. |
| 8 | **Contract (Pact)** | Liczba kontraktów | 3 | >= 6 | >= 8 | NBP API (3 mamy), Stripe webhook (2 nowe), e-Deklaracje XSD (1 nowy). |
| 9 | **Mutation (Infection)** | MSI | 76% | >= 80% | >= 85% | CI gate. 80% = branżowy standard FinTech. 85% = dojrzały produkt. >90% = overkill. |
| 10 | **Mutation** | MSI Domain-only | nieznane | >= 88% | >= 92% | Domain to core. Infection --filter=src/*/Domain. |
| 11 | **Security** | Liczba testów | 21 | >= 30 | >= 40 | Systematyczne mapowanie OWASP Top 10. Auth boundary testy. PII leak testy. |
| 12 | **Security** | OWASP coverage | ~5/10 (est.) | >= 8/10 | 10/10 | Każda kategoria OWASP Top 10 która nas dotyczy = min 1 test. |
| 13 | **E2E** | Liczba scenariuszy | 0 | >= 8 | >= 15 | KRYTYCZNY GAP. Happy path: upload → calc → preview → XML download. |
| 14 | **Smoke** | Liczba testów | 20+ | >= 20 | >= 25 | Wystarczające. Pokrywają boot, routing, basic responses. |
| 15 | **Canary** | Liczba testów | 3 | >= 5 | >= 7 | NBP API format, Stripe API, e-Deklaracje schema URL, SSL cert expiry. |
| 16 | **Chaos** | Liczba testów | 0 | >= 5 | >= 8 | DB timeout, Redis down, NBP 500, filesystem full, Stripe webhook fail. |
| 17 | **Load (k6)** | Liczba skryptów | 3 | >= 6 | >= 8 | Dodać: spike, soak, concurrent CSV import. |
| 18 | **Load** | p95 response time | nieznane | < 3s (50 users) | < 2s (500 users) | Beta = 50 concurrent. Produkcja sezon PIT = 500 concurrent. |
| 19 | **Load** | Error rate under load | nieznane | < 0.5% | < 0.1% | FinTech standard. |
| 20 | **Static analysis** | PHPStan errors | 0 | 0 | 0 | Level max, CI gate. Non-negotiable. |
| 21 | **Static analysis** | ECS violations | 0 | 0 | 0 | CI gate. |
| 22 | **Static analysis** | Deptrac violations | 0 | 0 | 0 | Architektura warstw. CI gate. |
| 23 | **Dependency audit** | Known CVEs | 0 | 0 | 0 | composer audit w CI. |

### 1.2. Metryki procesowe

| Metryka | Wartość obecna | Target Beta | Target Production |
|---|---|---|---|
| Flaky test rate | 0% (est.) | 0% | 0% |
| CI pipeline time (stage 1-3) | ~8 min | < 10 min | < 10 min |
| Time to fix P0 test failure | N/A | < 4h | < 2h |
| Test-to-code ratio (Domain/App) | ~1:1 | >= 1.2:1 | >= 1.5:1 |
| Golden dataset sign-off backlog | 0 | 0 | 0 |

---

## 2. Golden Dataset — lista reguł podatkowych (Tomasz)

| # | Reguła | Status |
|---|---|---|
| 1 | Podstawowy FIFO (buy + sell, 1 instrument) | MAMY |
| 2 | Cross-year FIFO (buy 2024, sell 2025) | MAMY |
| 3 | Fractional shares | MAMY |
| 4 | Multi-currency z przeliczeniem NBP (art. 11a) | CZĘŚCIOWO |
| 5 | Dywidendy krajowe (19% ryczałt, art. 30a) | MAMY |
| 6 | Dywidendy zagraniczne z UPO (cap WHT) | MAMY |
| 7 | Strata z lat ubiegłych (art. 9 ust. 3 — max 50%/rok, 5 lat) | MAMY |
| 8 | Kryptowaluty (art. 30b ust. 1a) | BRAK |
| 9 | Equity vs crypto vs dividends w PIT-38 (pola P_20-P_49) | CZĘŚCIOWO |
| 10 | Zaokrąglanie do pełnych złotych (art. 63 §1 Ordynacji) | MAMY |
| 11 | Zero-gain scenario (buy=sell, tax=0) | BRAK DEDYKOWANEGO |
| 12 | Tylko straty (brak zysku, P_24=0) | MAMY |
| 13 | Wiele brokerów w jednym roku | BRAK |
| 14 | Commission allocation across partial fills | MAMY |
| 15 | Same-day FIFO ordering (determinism) | MAMY |
| 16 | Extreme amounts (10M PLN, 0.01 PLN) | MAMY |
| 17 | Corporate actions (split, merger) | BRAK — nie implementujemy jeszcze |
| 18 | Short selling | BRAK — nie implementujemy jeszcze |
| 19 | Dywidendy z wielu krajów w jednym roku (PIT/ZG) | BRAK DEDYKOWANEGO |
| 20 | Strata + zysk w tym samym roku (kompensacja) | BRAK DEDYKOWANEGO |

---

## 3. Nowe poziomy testów do dodania

### 3.1. DODAĆ przed beta (P0/P1)

| Poziom | Opis | Effort | Priorytet |
|---|---|---|---|
| **PIT-38 XML Schema Validation** | Każdy wygenerowany XML przechodzi XSD schema validation e-Deklaracji MF. Gate w CI. | 4h | **P0** |
| **Approval/Snapshot Testing na XML** | Snapshot testy: wygenerowany XML porównywany ze zatwierdzonym wzorcem. Zmiana = explicit approve. | 6h | P1 |
| **CSV Fuzzing** | Losowe/zmanipulowane CSV → graceful error, nigdy crash/exception leak. | 8h | P1 |
| **Disclaimer Regression** | E2E: weryfikuje obecność disclaimera "nie stanowi doradztwa" na kluczowych stronach. | 2h | P1 |
| **Auth Boundary Regression** | Systematyczne 401/403 na KAŻDY chroniony endpoint bez sesji. Generator z route listy. | 4h | P1 |
| **PII Leak Detection** | NIP/email/imię NIE pojawiają się w response body (błędu), logach, stacktrace. | 4h | P1 |

### 3.2. DODAĆ po beta (P2)

| Poziom | Opis | Trigger |
|---|---|---|
| **Simulated Pentest (DAST)** | OWASP ZAP w nightly CI. | Przed pierwszym sezonem PIT (styczeń 2027) |
| **Drift Detection (ADR vs kod)** | Skrypt: ADR decisions → grep w repo. | Po stabilizacji architektury |
| **Soak Test (4h sustained load)** | k6 soak: wykrywa memory leaks. | Po migracji na ECS |
| **Compliance-as-Code (light)** | Smoke checks: encryption_key != default, NIP encrypted, CSRF enabled. | Przed produkcją |

### 3.3. NIE DODAWAĆ

| Poziom | Uzasadnienie |
|---|---|
| Regulatory Diff (kod vs ustawa) | Nie da się zautomatyzować. Golden datasets = odpowiednik. |
| Adversarial Review (red team) | Za drogie przed beta (invite-only). Po pierwszym sezonie. |
| Profesjonalny pentest | 15-30k PLN. ROI dopiero z revenue. |
| Full Chaos Engineering | Sensowne na ECS, nie na shared hosting (MyDevil). |

---

## 4. Trigger Matrix

| Zmiana | Wymagane testy | CI stage |
|---|---|---|
| **Domain logic** | unit + golden dataset + property + mutation | 1 + 2 |
| **FIFO matching** | unit + golden dataset + property (all FIFO) + mutation | 1 + 2 |
| **Tax calculation** | unit + golden dataset + mutation + XML schema validation | 1 + 2 |
| **PIT-38 XML generator** | unit + golden dataset + XML snapshot + XML schema validation | 1 + 2 |
| **New broker adapter** | unit + integration + contract (repository) + CSV fuzzing sample | 1 + 2 |
| **New API endpoint** | integration + auth boundary + security (CSRF, rate limit) + E2E (if user-facing) | 2 + 3 |
| **DB migration** | integration + performance (query explain) + rollback test | 2 |
| **Auth/Security change** | security suite + auth boundary regression + PII leak detection | 3 |
| **CSV upload/parsing** | unit + integration + CSV fuzzing + security (file size, MIME) | 1 + 2 + 3 |
| **NBP API integration** | unit + contract (Pact) + canary + chaos (timeout/500) | 1 + 2 + nightly |
| **Stripe integration** | unit + contract (Pact) + canary + chaos (webhook fail) | 1 + 2 + nightly |
| **UI/template change** | smoke + E2E (affected flow) + disclaimer regression | 3 |
| **Infrastructure/config** | smoke + compliance-as-code checks | 3 |
| **Performance-sensitive** | integration + load test (targeted) + performance benchmark | 2 + nightly |
| **Any change (always)** | PHPStan + ECS + Deptrac + composer audit | 1 |

---

## 5. Pragmatyczne ograniczenia

### Gdzie 80% jest lepsze niż 100%

| Metryka | Dlaczego NIE 100% | Sensowny target |
|---|---|---|
| Line coverage (Infrastructure) | Kontrolery, Doctrine mappings — unit ma niską wartość, integration pokrywa lepiej. | 65-70% |
| MSI (Infection) | Mutanty w getterach, konstruktorach — zabijanie ich wymaga trywialnych testów. | 80-85% |
| OWASP Top 10 coverage | A04/A08 trudne do automatyzacji. | 8/10 auto, 2/10 manual |
| E2E test count | Wolne, kruche, drogie w utrzymaniu. NIE testujemy każdej permutacji. | 8-15 |
| Contract tests | 2 external API (NBP, Stripe). Więcej niż 4 per API = over-specification. | 6-8 total |

### Metryki których NIE warto ścigać

| Metryka | Dlaczego | Co zamiast |
|---|---|---|
| 100% branch coverage | Unreachable error paths. ROI < 0 po 95%. | 90% Domain, 80% Application |
| Czas per test < X ms | Premature optimization. | CI pipeline < 10 min |
| MSI na Infrastructure | Boilerplate, nie logika. | Scope = unit testsuite |
| 100% golden dataset coverage | Reguły których nie implementujemy nie potrzebują datasetu. | Coverage implementowanych reguł |

---

## 6. Roadmap implementacji

### Phase 1: Pre-Beta Critical (do 01.2027)
1. PIT-38 XML Schema Validation — gate w CI **(P0)**
2. E2E happy path — 8 scenariuszy **(P0 gap)**
3. Golden datasets +9 — do 20 scenariuszy **(P1)**
4. Auth boundary regression — generator z route listy **(P1)**
5. MSI >= 80% — podniesienie z 76% **(P1)**
6. Property tests do 12 — FIFO + Money properties **(P1)**

### Phase 2: Pre-Season PIT (do 01.2027 — równolegle)
7. Chaos tests 5 — DB/Redis/NBP/filesystem/Stripe
8. XML Snapshot testing — approval-based
9. CSV Fuzzing — basic suite
10. PII Leak Detection — systematyczne
11. Disclaimer regression — E2E
12. Load tests: spike + soak — k6

### Phase 3: Post-Beta (03-04.2027)
13. DAST (OWASP ZAP) — nightly
14. Drift Detection — ADR vs kod
15. Compliance-as-Code light — smoke checks
16. Golden datasets do 30 — edge cases z realnych rozliczeń

---

## 7. Hexagonal Process Tests

Osobny katalog procesów biznesowych do testowania przez publiczne wejścia, porty i fake'i jest utrzymywany w [HEXAGONAL_PROCESS_TEST_PLAN.md](/Users/janbogdanski/projects/skrypty/fin/docs/HEXAGONAL_PROCESS_TEST_PLAN.md).

Ten plan nie zastępuje obecnych poziomów testów. Porządkuje tylko to, **które procesy biznesowe** mają być chronione testami unit pisanymi hexagonalnie, zamiast testów implementacyjnych typu "jedna klasa, jeden test".

Najwyższy priorytet wg tego planu:

1. dogrywanie kolejnego roku bez pełnego reimportu,
2. idempotentny replay historii FIFO,
3. roczna kalkulacja podatku ze stratami,
4. declaration gates (`NoData`, `PaymentRequired`, `ProfileIncomplete`).

---

*Zatwierdzone przez zespół 2026-04-03. Metryki są MIERZALNE i AUTOMATYZOWALNE — każda może być CI gate.*
