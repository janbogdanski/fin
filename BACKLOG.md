# TaxPilot — Product Backlog

Jedno źródło prawdy. Wszystkie findings z review, retro, QA, security, legal trafiają tutaj.

**Zasady:**
- Każdy item ma source (skąd przyszedł)
- P0 = bloker (fix TERAZ), P1 = before next release, P2 = tech debt, P3 = nice to have
- Zrobione → przenieś do DONE z datą
- Sprint assignment = kiedy planujemy

---

## P0 — Blockery

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P0-001 | DividendTaxService nie cappuje WHT do stawki UPO (art. 30a ust. 2) | Code Review S1+2, QA S3 | 4 | DONE |
| P0-002 | OpenPosition.reduceQuantity() brak guard na negative | Code Review S1+2, QA S3 | 4 | DONE |
| P0-003 | AuditReportGenerator używa bcmath zamiast brick/math + DRY violation | Code Review S1+2, Code S3 | 4 | DONE |
| P0-007 | Brak CSRF token na upload CSV form | Security S3 | 4 | DONE |
| P0-008 | Brak auth — access_control: [] (wszystkie endpointy publiczne) | Security S3 | 4 | DONE |
| P0-009 | registerSell() brak atomowości — partial fail = corrupted aggregate | QA S3 | 4 | DONE |

## P1 — Before Next Release

### Architecture (Code Review S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-027 | Reflection hack w DoctrineTaxPositionLedgerRepository → dodać reconstitute() | Code S3 | 4 | DONE |
| P1-028 | TaxPositionLedger hard-coupled do static CurrencyConverter → inject lub pre-convert | Code S3 | 4 | DONE |
| P1-029 | GetTaxSummaryHandler wywołuje Command handler → CQRS violation | Code S3 | 4 | DONE |
| P1-030 | AnnualTaxCalculation 388 linii, SRP violation → wydzielić snapshot DTO | Code S3 | 4 | TODO |
| P1-031 | Declaration\Domain importuje TaxCalc\Domain\Model → Dependency Rule violation | Code S3 | 4 | DONE |

### Security (Security Audit S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-032 | PII (NIP, imię) w preview bez auth | Security S3 | 4 | TODO |
| P1-033 | Weak/default .env keys (APP_SECRET, ENCRYPTION_KEY, NIP_HMAC_KEY) | Security S3 | 4 | DONE |
| P1-034 | CDN scripts (Tailwind, Skypack) bez SRI — supply chain risk | Security S3 | 4 | DONE |
| P1-035 | Brak security headers (CSP, X-Frame-Options, HSTS) | Security S3 | 4 | DONE |
| P1-036 | PIT38Data brak walidacji NIP/kwot → invalid XML dla e-Deklaracje | Security S3, QA S3 | 4 | DONE |
| P1-037 | DeclarationController exportXml() — raw XML concat zamiast generatora | Security S3 | 4 | DONE |

### Performance (Perf Review S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-038 | CSV explode() 50MB → 200MB RAM peak — brak streaming | Perf S3 | 4 | TODO |
| P1-039 | FIFO usort() po każdym registerBuy() → O(N * n log n) | Perf S3 | 4 | DONE |
| P1-040 | removeOpenPosition() array_filter O(n) per remove → O(K*N) total | Perf S3 | 4 | DONE |
| P1-041 | syncOpenPositions DELETE ALL + INSERT ALL → batch UPSERT | Perf S3 | 4 | DONE |
| P1-042 | Brak composite index (isin, sell_date) na closed_positions | Perf S3 | 4 | DONE |
| P1-043 | getRatesForDateRange() nie cachowane — 250 HTTP calls cold start | Perf S3 | 4 | DONE |
| P1-044 | insertClosedPositions individual INSERT → batch multi-row | Perf S3 | 4 | DONE |

### QA (QA Audit S3)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-045 | Same-date buy FIFO ordering non-deterministic (usort instability) | QA S3 | 4 | DONE |
| P1-046 | Revolut brak ISIN → cross-broker FIFO nie działa | QA S3 | 4 | DONE |
| P1-047 | CalculateAnnualTaxHandler pomija prior year losses | QA S3 | 4 | DONE |
| P1-048 | Brak testu: buy 50, sell 100 (partial consume + exception + state) | QA S3 | 4 | DONE |

### Existing P1 (from S1+2)

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-010 | CSRF on upload endpoint | Security S1+2 | 4 | MERGED → P0-007 |
| P1-011 | NIP value object z walidacją (check digit) | Code S1+2 | 4 | MERGED → P1-036 |
| P1-012 | Revolut: 1 warning per import, nie per transaction | Code S1+2 | 4 | DONE |
| P1-013 | easter_date() → easter_days() (32-bit safety) | Code S1+2 | 4 | DONE |
| P1-014 | CachedProvider cachuje po transactionDate, nie effectiveDate | Code S1+2 | 4 | DONE |
| P1-015 | Duplicate detection na import | Sprint 2 debt | 5 | TODO |
| P1-016 | Stripe billing integration | Plan | 5 | TODO |
| P1-017 | Wiring: Import → Calculate → Declaration (full flow) | Retro S1+2 | 4 | TODO |
| P1-018 | Redis auth + TLS (produkcja) | Security S1+2 | 5 | TODO |
| P1-019 | File size limit: 50MB → 5MB | Security S1+2 | 4 | MERGED → P1-038 |
| P1-020 | Degiro supports() false positive | Code S1+2 | 5 | TODO |
| P1-021 | equityLossDeduction nie waliduje czy > equityGainLoss | Code S1+2 | 5 | TODO |
| P1-022 | ImportController test (WebTestCase) | QA S1+2 | 5 | TODO |
| P1-023 | UTF-8 BOM handling w adapterach | QA S1+2 | 5 | TODO |
| P1-024 | ClosedPosition gainLoss invariant check | QA S1+2 | 5 | TODO |
| P1-025 | Wyrównanie testów adapterów do poziomu IBKR | QA S1+2 | 5 | TODO |
| P1-026 | Audit trail tamper-proof | Security S0 | 5 | TODO |

## P2 — Tech Debt

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P2-001 | IMPLEMENTATION_PLAN.md — usunąć TypeScript blok | Review S0 | — | TODO |
| P2-002 | EVENT_STORMING.md — usunąć zdarzenie #117 | Review S0 | — | TODO |
| P2-003 | AdapterRegistry kolejność niedeterministyczna (priority) | Code S1+2 | — | TODO |
| P2-004 | AnnualTaxCalculation = "God Aggregate" → Basket VOs | Code S1+2 | — | MERGED → P1-030 |
| P2-005 | UPORegistry hardcoded → config/database | Code S1+2 | — | TODO |
| P2-006 | Naming collision: 2× PriorYearLoss | Code S1+2 | — | TODO |
| P2-007 | getRatesForDateRange() nie cachowane | Code S1+2 | — | MERGED → P1-043 |
| P2-008 | Explicit timezone handling (Europe/Warsaw) | Code S1+2 | — | TODO |
| P2-009 | Property-based tests dla FIFO | QA S1+2 | — | TODO |
| P2-010 | AuditReport: Declaration DTO zamiast ClosedPosition coupling | Code S1+2 | — | MERGED → P1-031 |
| P2-011 | Test PriorYearLoss walidacji constructor | QA S1+2 | — | TODO |
| P2-012 | Test TaxYear walidacji | QA S1+2 | — | TODO |
| P2-013 | Test idempotentności finalize() | QA S1+2 | — | TODO |
| P2-015 | Docker-compose: .env file zamiast hardcoded credentials | Security S1+2 | — | MERGED → P1-033 |
| P2-016 | MIME type check: walidacja rozszerzenia .csv | Security S1+2 | — | TODO |
| P2-017 | Information disclosure w exception messages | Security S1+2 | — | TODO |
| P2-018 | CSV sanitize: dodać \n do stripowanych znaków | Security S1+2 | — | TODO |
| P2-019 | Rate limiting na upload endpoint | Security S1+2 | — | TODO |
| P2-020 | DashboardController mock data → wydzielić do provider | Code S3 | — | TODO |
| P2-021 | DeclarationController mock XML → podłączyć PIT38XMLGenerator | Code S3 | — | TODO |
| P2-022 | Revolut 500 warnings → 1 metadata notice | Code S3 | — | MERGED → P1-012 |
| P2-023 | UserId/TransactionId Symfony Uid dependency w Domain layer | Code S3 | — | TODO |
| P2-024 | CurrencyConverter static → instancyjny serwis (testowalność) | Code S3 | — | MERGED → P1-028 |
| P2-025 | DoctrineUserRepository flush() w repo → Unit of Work w handler | Code S3 | — | TODO |
| P2-026 | Degiro supports() — wydzielić metody z intencją (DRY) | Code S3 | — | TODO |
| P2-027 | buildResult() DRY violation (4 adaptery) → ParseResultBuilder | Code S3 | — | TODO |
| P2-028 | Upload CSV 50MB limit → 5-10MB + row count limit | Security S3 | — | TODO |
| P2-029 | MIME: usunąć application/vnd.ms-excel z dozwolonych | Security S3 | — | TODO |
| P2-030 | User::register() error message ujawnia email (PII) | Security S3 | — | TODO |
| P2-031 | NBP API brak max response size | Security S3 | — | TODO |
| P2-032 | CORS config (przygotować na API endpoints) | Security S3 | — | TODO |
| P2-033 | CalculateAnnualTaxHandler → aggregate SQL instead of loading all objects | Perf S3 | — | TODO |
| P2-034 | AuditReportGenerator → StreamedResponse | Perf S3 | — | TODO |
| P2-035 | ReflectionProperty cache w loadOpenPositions | Perf S3 | — | TODO |
| P2-036 | NBP fallback worst case 21 HTTP requests per date | Perf S3 | — | TODO |
| P2-037 | CsvSanitizer ltrim strips leading dash in negative numbers | QA S3 | — | TODO |
| P2-038 | PIT38XMLGenerator double escaping risk (htmlspecialchars + DOMDocument) | QA S3 | — | TODO |
| P2-039 | Test: equity loss scenario (P_24=0, P_25=loss) | QA S3 | — | TODO |
| P2-040 | Test: applyPriorYearLosses deduction > gain (waste detection) | QA S3 | — | TODO |
| P2-041 | Test: double finalize() → LogicException | QA S3 | — | TODO |
| P2-042 | Test: addClosedPositions([]) empty array noop | QA S3 | — | TODO |
| P2-043 | IBKR parseDateTime milisecond format | QA S3 | — | TODO |

## P3 — Nice to Have

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P3-001 | Canary test: live NBP API format check (CI nightly) | ADR-019 | — | TODO |
| P3-002 | Community reporting: "format nie działa" button | ADR-019 | — | TODO |
| P3-003 | Open-source adapter SDK | ADR-019 | — | TODO |
| P3-004 | Test very large amounts (10M PLN) | QA S1+2 | — | TODO |
| P3-005 | Test very small amounts (0.01 PLN) | QA S1+2 | — | TODO |
| P3-006 | CSV with only headers, no data | QA S1+2 | — | TODO |
| P3-007 | DividendCountrySummary::add() error test | QA S1+2 | — | TODO |
| P3-008 | Integration test: AdapterRegistry + real adapters | QA S1+2 | — | TODO |

## BLOCKED — External Dependencies

| ID | Opis | Blocked by | Status |
|---|---|---|---|
| BLK-001 | XTB adapter | Real CSV od PO | WAITING |
| BLK-002 | mBank eMakler adapter | Real CSV od PO | WAITING |
| BLK-003 | Opinia prawna: narzędzie vs doradztwo | Kancelaria FinTech | WAITING |
| BLK-004 | DPIA (GDPR Art. 35) | Audytor zewnętrzny | WAITING |

---

## DONE

| ID | Opis | Sprint | Done |
|---|---|---|---|
| ~~P0-004~~ | Golden dataset test #1 (Tomasz) | 3 | 2026-04-02 |
| ~~P0-005~~ | CalculateAnnualTaxHandler test | 3 | 2026-04-02 |
| ~~P0-006~~ | Zero quantity guard | 3 | 2026-04-02 |
| ~~P1-001~~ | Symfony kernel bootstrap | 3 | 2026-04-02 |
| ~~P1-002~~ | Doctrine setup + XML mappings | 3 | 2026-04-02 |
| ~~P1-004~~ | Dashboard UI | 3 | 2026-04-02 |
| ~~P1-005~~ | PIT-38 preview w UI | 3 | 2026-04-02 |
| ~~P1-006~~ | Cross-year FIFO test (Golden #2) | 3 | 2026-04-02 |
| ~~P1-007~~ | Fractional shares test | 3 | 2026-04-02 |
| ~~P1-008~~ | XXE defense | 3 | 2026-04-02 |
| ~~P1-009~~ | CsvSanitizer trait DRY | 3 | 2026-04-02 |
| ~~B-01~~ | Fix commission allocation | Pre-sprint | 2026-04-02 |
| ~~B-02~~ | Money.toPLN currency guard | Pre-sprint | 2026-04-02 |
| ~~B-03~~ | ADR-017 Multi-Year FIFO | Pre-sprint | 2026-04-02 |
| ~~B-04~~ | Fix zaokrąglanie art. 63 §1 | Pre-sprint | 2026-04-02 |
| ~~B-05~~ | closedPositions append-only | Pre-sprint | 2026-04-02 |
| ~~B-11~~ | Money::of() nie zaokrągla | Pre-sprint | 2026-04-02 |
| ~~ADR-012~~ | PII Encryption | Pre-sprint | 2026-04-02 |
| ~~ADR-013~~ | Data Retention & GDPR | Pre-sprint | 2026-04-02 |
| ~~ADR-014~~ | Secrets Management | Pre-sprint | 2026-04-02 |
| ~~ADR-015~~ | Authentication Security | Pre-sprint | 2026-04-02 |
| ~~ADR-016~~ | Timezone Handling | Pre-sprint | 2026-04-02 |
| ~~ADR-017~~ | Multi-Year FIFO | Pre-sprint | 2026-04-02 |
| ~~ADR-018~~ | CSV Upload Security | Pre-sprint | 2026-04-02 |
| ~~ADR-019~~ | Broker Adapter Versioning | Pre-sprint | 2026-04-02 |
