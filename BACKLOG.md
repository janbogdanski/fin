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
| P0-001 | DividendTaxService nie cappuje WHT do stawki UPO (art. 30a ust. 2) | Code Review S1+2 | 3 | IN PROGRESS (e2e-tests agent) |
| P0-002 | OpenPosition.reduceQuantity() brak guard na negative | Code Review S1+2 | 3 | IN PROGRESS (e2e-tests agent) |
| P0-003 | AuditReportGenerator używa bcmath zamiast brick/math | Code Review S1+2 | 3 | TODO |
| P0-004 | Brak end-to-end golden dataset test (full pipeline) | QA Review S1+2 B-01 | 3 | IN PROGRESS (e2e-tests agent) |
| P0-005 | Brak testu CalculateAnnualTaxHandler | QA Review S1+2 B-02 | 3 | IN PROGRESS (e2e-tests agent) |
| P0-006 | Zero quantity → division by zero (brak guard) | QA Review S1+2 C-01 | 3 | IN PROGRESS (e2e-tests agent) |

## P1 — Before Next Release

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P1-001 | Symfony kernel bootstrap | Retro S1+2 | 3 | IN PROGRESS (symfony-bootstrap agent) |
| P1-002 | Doctrine setup + entity mappings (XML) | Retro S1+2 | 3 | IN PROGRESS (symfony-bootstrap agent) |
| P1-003 | Auth: magic link login | Sprint 1 debt | 3 | IN PROGRESS (symfony-bootstrap agent) |
| P1-004 | Dashboard UI | Sprint 2 debt | 3 | DONE (dashboard-ui agent) |
| P1-005 | PIT-38 preview w UI | Sprint 2 debt | 3 | DONE (dashboard-ui agent) |
| P1-006 | Cross-year FIFO test | QA Review S1+2 B-04 | 3 | IN PROGRESS (e2e-tests agent) |
| P1-007 | Fractional shares test | QA Review S1+2 C-05 | 3 | IN PROGRESS (e2e-tests agent) |
| P1-008 | XXE defense: DOMDocument resolveExternals=false | Security Review S1+2 H1 | 3 | IN PROGRESS (e2e-tests agent) |
| P1-009 | CsvSanitizer trait (DRY, 5 adapterów) | Code Review S1+2 P1-6, Retro | 3 | IN PROGRESS (e2e-tests agent) |
| P1-010 | CSRF on upload endpoint | Security Review S1+2 M4 | 4 | TODO |
| P1-011 | NIP value object z walidacją (check digit) | Code Review S1+2 P1-2 | 4 | TODO |
| P1-012 | Revolut: 1 warning per import, nie per transaction (500 warnings!) | Code Review S1+2 P1-7 | 4 | TODO |
| P1-013 | easter_date() → easter_days() (32-bit safety) | Code Review S1+2 P1-8 | 4 | TODO |
| P1-014 | CachedProvider cachuje po transactionDate, nie effectiveDate | Code Review S1+2 P1-1 | 4 | TODO |
| P1-015 | Duplicate detection na import | Sprint 2 debt | 4 | TODO |
| P1-016 | Stripe billing integration | Plan | 4 | TODO |
| P1-017 | Wiring: Import → Calculate → Declaration (full flow) | Retro S1+2 | 3 | PARTIAL (bootstrap agent) |
| P1-018 | Redis auth + TLS (produkcja) | Security Review S1+2 H2 | 4 | TODO |
| P1-019 | File size limit: 50MB → 5MB | Security Review S1+2 M1 | 4 | TODO |
| P1-020 | Degiro supports() false positive — zbyt generyczne headery | Code Review S1+2 P1-4 | 4 | TODO |
| P1-021 | equityLossDeduction nie waliduje czy > equityGainLoss | Code Review S1+2 P1-5 | 4 | TODO |
| P1-022 | ImportController test (WebTestCase) | QA Review S1+2 B-05 | 4 | TODO |
| P1-023 | UTF-8 BOM handling w adapterach | QA Review S1+2 C-09 | 4 | TODO |
| P1-024 | ClosedPosition gainLoss invariant check w constructor | QA Review S1+2 C-10 | 4 | TODO |
| P1-025 | Wyrównanie testów adapterów do poziomu IBKR | QA Review S1+2 R-03 | 4 | TODO |
| P1-026 | Audit trail tamper-proof (REVOKE UPDATE/DELETE) | Security Review S0 | 5 | TODO |

## P2 — Tech Debt

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P2-001 | IMPLEMENTATION_PLAN.md — usunąć stary blok TypeScript | Review S0 B-06 | — | TODO |
| P2-002 | EVENT_STORMING.md — usunąć zdarzenie #117 (optymalizacja podatkowa) | Review S0 B-09 | — | TODO |
| P2-003 | AdapterRegistry kolejność adapterów niedeterministyczna (priority) | Code Review S1+2 P2-1 | — | TODO |
| P2-004 | AnnualTaxCalculation = "God Aggregate" → rozbić na Basket VOs | Code Review S1+2 P2-3 | — | TODO |
| P2-005 | UPORegistry hardcoded → config/database | Code Review S1+2 P2-4 | — | TODO |
| P2-006 | Naming collision: 2× PriorYearLoss (TaxCalc + Declaration) | Code Review S1+2 P2-7 | — | TODO |
| P2-007 | getRatesForDateRange() nie cachowane | Code Review S1+2 P2-8 | — | TODO |
| P2-008 | Explicit timezone handling (Europe/Warsaw) wszędzie | Code Review S1+2 P2-6 | — | TODO |
| P2-009 | Property-based tests dla FIFO | QA Review S1+2 N-01 | — | TODO |
| P2-010 | AuditReport: Declaration DTO zamiast ClosedPosition (coupling) | Code Review S1+2 P1-3 | — | TODO |
| P2-011 | Test PriorYearLoss walidacji constructor | QA Review S1+2 N-03 | — | TODO |
| P2-012 | Test TaxYear walidacji | QA Review S1+2 N-04 | — | TODO |
| P2-013 | Test idempotentności finalize() | QA Review S1+2 N-05 | — | TODO |
| P2-014 | OpenPosition.reduceQuantity() guard na negative | QA Review S1+2 N-06 | — | MERGED → P0-002 |
| P2-015 | Docker-compose: .env file zamiast hardcoded credentials | Security Review S1+2 H2 | — | TODO |
| P2-016 | MIME type check: dodać walidację rozszerzenia pliku (.csv) | Security Review S1+2 M2 | — | TODO |
| P2-017 | Information disclosure w exception messages | Security Review S1+2 M3 | — | TODO |
| P2-018 | CSV sanitize: dodać \n do stripowanych znaków | Security Review S1+2 L1 | — | TODO |
| P2-019 | Rate limiting na upload endpoint | Security Review S1+2 L3 | — | TODO |

## P3 — Nice to Have

| ID | Opis | Source | Sprint | Status |
|---|---|---|---|---|
| P3-001 | Canary test: live NBP API format check (CI nightly) | ADR-019 | — | TODO |
| P3-002 | Community reporting: "format nie działa" button + anonymized upload | ADR-019 | — | TODO |
| P3-003 | Open-source adapter SDK (community-driven adapters) | ADR-019 | — | TODO |
| P3-004 | Test very large amounts (10M PLN) | QA Review S1+2 C-07 | — | TODO |
| P3-005 | Test very small amounts (0.01 PLN) | QA Review S1+2 C-06 | — | TODO |
| P3-006 | CSV with only headers, no data (all adapters) | QA Review S1+2 C-08 | — | TODO |
| P3-007 | DividendCountrySummary::add() error test (different countries) | QA Review S1+2 N-02 | — | TODO |
| P3-008 | Integration test: AdapterRegistry + real adapters + fixtures | QA Review S1+2 N-08 | — | TODO |

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
| ~~B-01~~ | Fix commission allocation (commissionPerUnitPLN) | Pre-sprint | 2026-04-03 |
| ~~B-02~~ | Money.toPLN currency validation guard | Pre-sprint | 2026-04-03 |
| ~~B-03~~ | ADR-017 Multi-Year FIFO | Pre-sprint | 2026-04-03 |
| ~~B-04~~ | Fix zaokrąglanie art. 63 §1 | Pre-sprint | 2026-04-03 |
| ~~B-05~~ | closedPositions append-only | Pre-sprint | 2026-04-03 |
| ~~B-11~~ | Money::of() nie zaokrągla, Money::rounded() na granicach | Pre-sprint | 2026-04-03 |
| ~~ADR-012~~ | PII Encryption | Pre-sprint | 2026-04-03 |
| ~~ADR-013~~ | Data Retention & GDPR | Pre-sprint | 2026-04-03 |
| ~~ADR-014~~ | Secrets Management | Pre-sprint | 2026-04-03 |
| ~~ADR-015~~ | Authentication Security | Pre-sprint | 2026-04-03 |
| ~~ADR-016~~ | Timezone Handling | Pre-sprint | 2026-04-03 |
| ~~ADR-017~~ | Multi-Year FIFO | Pre-sprint | 2026-04-03 |
| ~~ADR-018~~ | CSV Upload Security | Pre-sprint | 2026-04-03 |
| ~~ADR-019~~ | Broker Adapter Versioning & Maintenance | Pre-sprint | 2026-04-03 |
