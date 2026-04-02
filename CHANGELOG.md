# TaxPilot — Changelog & Progress Tracker

---

## Sprint 3 (2026-04-03) — "Działający Produkt"

### Delivered

**Symfony Bootstrap**
- [x] Symfony Kernel + config (bundles, framework, doctrine, messenger, cache, security)
- [x] public/index.php + bin/console
- [x] .env template + .env.test
- [x] config/services.yaml (autowiring, interface aliases)
- [x] config/routes.yaml (attribute routing)

**Doctrine ORM**
- [x] 6 Custom Doctrine Types (UserId, ISIN, MoneyAmount, CurrencyCode, TransactionId, BrokerId)
- [x] XML mappings: User, TaxPositionLedger, OpenPosition, ClosedPosition
- [x] DoctrineUserRepository (ORM-based)
- [x] DoctrineTaxPositionLedgerRepository (DBAL-based — composite VOs)
- [x] Migration: 4 tabele (users, tax_position_ledgers, open_positions, closed_positions)

**Identity BC**
- [x] User domain entity (pure PHP)
- [x] SecurityUser adapter (Symfony Security)
- [x] UserProvider
- [x] Minimal security firewall config

**Dashboard UI**
- [x] DashboardController (4 routes: dashboard, calculation, fifo, dividends)
- [x] DeclarationController (4 routes: preview, export XML, export PDF, PIT/ZG)
- [x] 8 Twig templates (dashboard, calculation, fifo, dividends, PIT-38 preview, PIT/ZG, disclaimer partial)
- [x] 3 Stimulus controllers (year selector, table sort, filter)
- [x] Updated base.html.twig (full nav, mobile-responsive)
- [x] Mock data w controllerach (ready for real wiring)

**Quality — Findings Fixed**
- [x] Guard clauses: quantity > 0, price >= 0 (P0-006)
- [x] Golden Dataset #001 Tomasz E2E test (P0-004)
- [x] Golden Dataset #002 Cross-Year FIFO test (P1-006)
- [x] CalculateAnnualTaxHandler test (P0-005)
- [x] Fractional shares test (P1-007)
- [x] Zero gain test
- [x] Multiple sells same day test
- [x] CsvSanitizer trait — DRY (P1-009)
- [x] XXE defense in XML generators (P1-008)
- [x] 4 integration smoke tests (kernel boots, Doctrine, container services)

### Metryki

| Metryka | Sprint 1+2 | Sprint 3 | Delta |
|---|---|---|---|
| Tests | 233 | 250 | +17 |
| Assertions | 742 | 840 | +98 |
| PHPStan | 0 | 0 | = |
| ECS | 0 | 0 | = |
| Deptrac | 0 | 0 | = |
| Bounded Contexts z kodem | 4 | 5 (+Identity) | +1 |
| Symfony kernel | NO | YES | ★ |
| Doctrine + migrations | NO | YES | ★ |
| Demowalny UI | NO | YES (mock data) | ★ |

---

## Sprint 1+2 (2026-04-03) — "CSV to Number" + "Multi-Broker + Dividends"

### Delivered

**Domain Core**
- [x] Money value object (brick/math, rounded(), NO eager rounding — B-11 fix)
- [x] ISIN value object (Luhn check digit validation)
- [x] NBPRate value object (validated factory, private constructor)
- [x] CurrencyConverter service (toPLN with currency guard — B-02 fix)
- [x] TaxPositionLedger aggregate (FIFO cross-year, cross-broker — B-03 fix)
- [x] OpenPosition (originalQuantity + commissionPerUnitPLN — B-01 fix)
- [x] ClosedPosition (append-only, not loaded into aggregate — B-05 fix)
- [x] DividendTaxService + UPO Registry (15 krajów, 19% - WHT)
- [x] LossCarryForwardPolicy (5 lat, 50%, osobne koszyki)
- [x] TaxRoundingPolicy (art. 63 §1, matematyczne — B-04 fix)
- [x] AnnualTaxCalculation aggregate (3 koszyki, finalize, CQRS)

**Broker Adapters (ACL)**
- [x] IBKR Activity Statement (sekcyjny CSV, trades + dividends + WHT)
- [x] Degiro Transactions (EN/NL, flat CSV)
- [x] Degiro Account Statement (dividends + WHT)
- [x] Revolut Stocks (no ISIN — warning, multi-date-format)
- [x] Bossa History (semicolon, Windows-1250, polski decimal)
- [x] AdapterRegistry (auto-detect broker from CSV content)
- [ ] XTB — BLOCKED (czeka na real CSV)
- [ ] mBank eMakler — BLOCKED (czeka na real CSV)

**Exchange Rates**
- [x] NBP API Client (retry, fallback, timeout)
- [x] CachedExchangeRateProvider (Redis decorator, TTL 30d)
- [x] PolishWorkingDayResolver (holidays, weekends, max 7 days back)

**Declaration**
- [x] PIT-38 XML Generator (wersja 17, e-Deklaracje format)
- [x] PIT/ZG XML Generator (per kraj)
- [x] Audit Trail HTML Generator (FIFO table, per instrument, XSS safe)

**CQRS (Application)**
- [x] CalculateAnnualTax Command + Handler
- [x] GetTaxSummary Query + Handler + Result DTO
- [x] Ports: ClosedPositionQueryPort, DividendResultQueryPort

**UI**
- [x] Upload page (drag&drop, Stimulus controller, Tailwind)
- [x] Results page (transactions table, errors, metadata)
- [x] Base layout (Tailwind CDN)

**Infrastructure**
- [x] Docker Compose (PHP 8.4, PostgreSQL 17, Redis 7, Mailpit)
- [x] Makefile (dev/test/lint/stan/deptrac/deploy)
- [x] PHPStan level max — 0 errors
- [x] ECS (PER-CS 2.0, strict, cleanCode) — 0 errors
- [x] Deptrac (layer dependency analysis) — 0 violations

**Documentation**
- [x] 19 ADR-ów (ADR-001 do ADR-019)
- [x] EVENT_STORMING.md (118 zdarzeń, 31 hotspotów)
- [x] ARCHITECTURE.md (Clean Arch, CQRS, DDD)
- [x] PLAN_PRAC.md (timeline, sprinty, budżet)
- [x] REVIEW_CONSOLIDATED.md (zbiorczy review)
- [x] REVIEW_LEGAL_TAX.md (prawnik + doradca podatkowy)

### Not Delivered
- [ ] Auth (magic link login)
- [ ] Persistence (Doctrine ORM, migrations, database)
- [ ] Symfony kernel bootstrap (routing, config, services)
- [ ] Dashboard UI (podsumowanie roku)
- [ ] PIT-38 preview w UI
- [ ] Duplicate detection
- [ ] Stripe billing

### Metryki

| Metryka | Wartość |
|---|---|
| Tests | 233 |
| Assertions | 742 |
| PHP files (src) | ~50 |
| Test files | ~20 |
| PHPStan | 0 errors (level max) |
| ECS | 0 errors |
| Deptrac | 0 violations |
| Broker adapters | 5 done + 2 blocked |
| ADRs | 19 |
| Velocity | 73% (16/22 stories) |

### Review Findings (do Sprint 3 backlogu)

**From Code Review:**
- Duplikacja `sanitize()` w 5 adapterach → wyciągnąć do CsvSanitizer trait
- Brak Symfony kernel = brak demowalnego produktu

**From Security Audit:**
- H1: XXE defense-in-depth w XML generators
- H2: Klucze szyfrowania w docker-compose.yml (dev-only ale ryzykowne)
- M1-M5: file size limit, MIME bypass, CSRF, Redis auth

**From QA:**
- B-01: Brak end-to-end golden dataset test (full pipeline)
- B-02: Brak testu CalculateAnnualTaxHandler
- B-04: Brak testu cross-year FIFO
- C-01: Zero quantity → division by zero (brak guard)
- C-05: Fractional shares (brak testu)
- C-09: UTF-8 BOM handling
- R-03: Adaptery Bossa/Revolut mają mniej testów niż IBKR

### Retro Action Items
1. Symfony bootstrap NAJPIERW w Sprint 3
2. Wspólny CsvSanitizer trait
3. Integration test: CSV → PIT-38
4. Agenci dostają brief o istniejących helpers
5. Scope freeze per sprint
6. Demo musi działać w przeglądarce

---

## Sprint 0 (2026-04-01 — 2026-04-03) — "Event Storming & Architecture"

### Delivered
- [x] 2-dniowy Event Storming (Brandolini + Mariusz Gil)
- [x] 118 Domain Events, 31 Hotspotów, 8 Bounded Contexts
- [x] Business Case (3 persony, TAM/SAM/SOM, model freemium)
- [x] Risk Register (25 ryzyk)
- [x] 25 User Stories z Given/When/Then
- [x] Architecture: Clean Arch + CQRS + DDD + TDD
- [x] ADR-001 do ADR-011 (11 ADR-ów)
- [x] Review pipeline: 4 agentów (code, security, QA, legal/tax)
- [x] 11 blokerów zidentyfikowanych i 7 naprawionych
- [x] Stack decision: PHP 8.4 + Symfony 7.2 + MyDevil
- [x] ADR-012 do ADR-018 (7 nowych po review)
- [x] Conditional GO decision

### Key Decisions
- MyDevil na start (1k PLN/rok), AWS na scale (ADR-009)
- Modular monolith, nie microservices (ADR-001)
- Twig + Hotwire, nie SPA (ADR-005)
- brick/math BigDecimal, nigdy float (ADR-006)
- Zaokrąglanie matematyczne art. 63 §1 (ADR-006 updated)
- FIFO cross-year, aggregate per (UserId, ISIN) (ADR-017)

---

## Backlog — Sprint 3

| Priorytet | Story | Owner | Source |
|---|---|---|---|
| P0 | Symfony kernel bootstrap | Bartek + Marek | Retro |
| P0 | Doctrine setup + entity mappings | Marek | Retro |
| P0 | Database migrations | Marek | Plan |
| P0 | Auth: magic link login | Marek | Sprint 1 debt |
| P0 | End-to-end golden dataset test | Kasia [QA] | QA review B-01 |
| P0 | Guard: quantity > 0, price >= 0 | Marek | QA review C-01 |
| P1 | Persistence: repositories | Ania | Plan |
| P1 | Wiring: Import → Calculate → Declaration | Marek | Retro |
| P1 | Dashboard UI | Zofia + Paweł | Sprint 2 debt |
| P1 | PIT-38 preview w UI | Paweł | Sprint 2 debt |
| P1 | Cross-year FIFO test | Kasia | QA review B-04 |
| P1 | CalculateAnnualTaxHandler test | Kasia | QA review B-02 |
| P1 | XXE defense: DOMDocument | Michał P. | Security H1 |
| P1 | CSRF on upload | Michał P. | Security M4 |
| P2 | CsvSanitizer trait (DRY) | Ania | Code review |
| P2 | UTF-8 BOM handling | Ania | QA review C-09 |
| P2 | Fractional shares test | Kasia | QA review C-05 |
| P2 | Duplicate detection | Ania | Sprint 2 debt |
| BLOCKED | XTB adapter | Czeka na CSV | |
| BLOCKED | mBank adapter | Czeka na CSV | |
