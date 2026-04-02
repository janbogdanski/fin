# Sprint 1+2 Review & Retrospective

**Data:** 2026-04-03
**Zespół:** Fully Featured AI Agent Team
**Sprinty:** 1 ("CSV to Number") + 2 ("Multi-Broker + Dividends") — połączone w jedną sesję

---

## SPRINT REVIEW — co dostarczyliśmy?

### Demo

**Scenariusz demo:** User wgrywa CSV z IBKR → system rozpoznaje brokera → parsuje transakcje → oblicza FIFO → przelicza kursem NBP → pokazuje zysk/stratę → generuje PIT-38 XML.

### Delivered vs. Planned

| Story | Plan | Status | Uwagi |
|---|---|---|---|
| Infra: Symfony + Docker + CI | Sprint 1 | ✅ DONE | Docker Compose, Makefile, PHPStan, ECS, Deptrac |
| Auth: magic link | Sprint 1 | ❌ NOT STARTED | Brak Symfony kernel/Security bundle setup |
| Domain: Money, ISIN, NBPRate | Sprint 1 | ✅ DONE | Z fixami z review (toPLN guard, Luhn, rounding) |
| Domain: TaxPositionLedger (FIFO) | Sprint 1 | ✅ DONE | Cross-year, cross-broker, commission fix |
| NBP API client + cache | Sprint 1 | ✅ DONE | Retry, fallback, holidays, Redis decorator |
| Import: IBKR adapter | Sprint 1 | ✅ DONE | Trades + dividends + WHT |
| Import: Degiro adapter | Sprint 2 | ✅ DONE | 2 adaptery (transactions + account statement), EN/NL |
| Import: XTB adapter | Sprint 2 | ❌ BLOCKED | Czeka na real CSV od Product Owner |
| Import: mBank adapter | Dodany | ❌ BLOCKED | Czeka na real CSV od Product Owner |
| Import: Revolut adapter | Dodany | ✅ DONE | Brak ISIN (warning), multi-date-format |
| Import: Bossa adapter | Dodany | ✅ DONE | Semicolon, Windows-1250, polski decimal |
| Domain: Cross-broker FIFO | Sprint 2 | ✅ DONE | Per ISIN, not per broker |
| Domain: DividendTaxService + UPO | Sprint 2 | ✅ DONE | 15 krajów, WHT, 19% - WHT |
| Domain: LossCarryForwardPolicy | Sprint 2 | ✅ DONE | 5 lat, 50%, osobne koszyki |
| Domain: TaxRoundingPolicy | Sprint 2 | ✅ DONE | Art. 63 §1, matematyczne |
| Domain: AnnualTaxCalculation | Sprint 2 | ✅ DONE | 3 koszyki, finalize, CQRS |
| Declaration: PIT-38 XML | Sprint 3 (pulled) | ✅ DONE | Wersja 17, e-Deklaracje format |
| Declaration: PIT/ZG XML | Sprint 3 (pulled) | ✅ DONE | Per kraj |
| Declaration: Audit trail HTML | Sprint 3 (pulled) | ✅ DONE | FIFO table, per instrument, XSS safe |
| UI: Upload + results | Sprint 2 | ✅ DONE | Drag&drop, Stimulus, Tailwind, auto-detect broker |
| UI: PIT-38 preview | Sprint 2 | ❌ NOT STARTED | Brak controller + template |
| UI: Dashboard | Sprint 2 | ❌ NOT STARTED | Brak persistence layer |
| Audit: basic trail | Sprint 2 | ✅ PARTIAL | Generator gotowy, brak integracji z UI |
| Duplicate detection | Sprint 2 | ❌ NOT STARTED | |

### Metryki

| Metryka | Wartość |
|---|---|
| Testy | 233 |
| Asercje | 742 |
| Pliki PHP (src) | ~50 |
| Pliki testów | ~20 |
| PHPStan level max | 0 errors |
| ECS | 0 errors |
| Deptrac violations | 0 |
| Broker adaptery | 5 (+ 2 blocked na CSV) |
| Bounded Contexts z kodem | 4/8 (TaxCalc, BrokerImport, ExchangeRate, Declaration) |
| Bounded Contexts bez kodu | 4/8 (Identity, Audit, Billing, Instrument Registry) |

### Co NIE dostarczyliśmy (i dlaczego)

1. **Auth (magic link)** — brak Symfony kernel bootstrap. Skupiliśmy się na domain logic. Auth jest infrastructure concern — dojdzie w następnym sprincie.
2. **Persistence (Doctrine)** — zero Doctrine mappings, zero migracji, zero bazy. Cały kod jest in-memory. Świadoma decyzja: domain first, persistence second.
3. **XTB + mBank adaptery** — blocked na real CSV od PO.
4. **Dashboard UI** — bez persistence nie ma skąd czytać danych.
5. **Duplicate detection** — nie zmieściliśmy się.
6. **Stripe billing** — za wcześnie.

---

## SPRINT RETROSPECTIVE

### Co poszło dobrze? 👍

1. **Równoległość agentów** — 3 agentów na adapterach jednocześnie (Degiro, Revolut+Bossa, Registry+UI) = dostarczenie w jednej rundzie zamiast trzech. Analogicznie 3 agentów na domain (Dividend, AnnualCalc, PIT-38).

2. **TDD discipline** — KAŻDY agent pisał testy PRZED implementacją. 233 testów to nie afterthought — to specyfikacja. Golden dataset #1 (Tomasz example) weryfikuje matematykę end-to-end.

3. **Clean Architecture enforced** — Deptrac w pipeline łapał naruszenia Dependency Rule natychmiast. Przeniesienie BrokerId/TransactionId/NBPRate do Shared Kernel było wymuszone przez tooling, nie przez code review.

4. **Integracja multi-agent** — po każdej rundzie agentów, integracja (fix PHPStan, ECS, Deptrac) zajmowała 5-15 minut. Pipeline jako arbiter jakości.

5. **Review pipeline z Event Stormingu** — 4 agentów reviewujących (code, QA, security, legal) = 11 blokerów znalezionych ZANIM napisaliśmy linię kodu. Poprawki zaokrąglania (art. 63 §1) i multi-year FIFO uratowały miesiące refaktoryzacji.

### Co poszło źle? 👎

1. **IMPLEMENTATION_PLAN wciąż ma TypeScript** — B-06 z review. Stary blok kodu nie został wyczyszczony. To sprawia że dokument jest niespójny.

2. **Brak Symfony kernel** — mamy 50 plików PHP ale zero konfiguracji Symfony. Brak `config/`, brak `public/index.php`, brak service wiring. To znaczy że nic nie działa jako aplikacja — tylko testy przechodzą. Trzeba było zacząć od `symfony new` i dodawać kod do działającego szkieletu.

3. **Agenci duplikowali patterns** — `sanitize()` skopiowany 5 razy w 5 adapterach. `buildResult()` pattern powtórzony. Brak wspólnego trait/helper bo każdy agent pisał niezależnie. Tech debt.

4. **Brak integration testów** — 233 unit testów, 0 integration. Nie wiemy czy AdapterRegistry + adapter + FIFO + NBP + AnnualCalc działa razem. Testujemy warstwy osobno, ale nie flow.

5. **Scope creep** — pullnęliśmy PIT-38 XML z Sprintu 3 do Sprintu 2. Efekt: nie zmieściliśmy auth, persistence, duplicate detection, dashboard. Better to finish Sprint 2 scope than start Sprint 3 scope.

6. **Brak persistence = brak demowalnego produktu** — nie możemy pokazać "user wgrywa CSV i dostaje PIT-38". Możemy pokazać "unit testy przechodzą". To nie jest demo.

### Co poprawić? 🔧

| Akcja | Kto | Kiedy |
|---|---|---|
| **Symfony bootstrap NAJPIERW** — `symfony new`, config, routing, kernel. Potem domain. | Bartek [devops] + Marek | Sprint 3, dzień 1 |
| **Wspólny trait `CsvSanitizer`** — wyciągnąć `sanitize()` z 5 adapterów do jednego miejsca | Ania [data] | Sprint 3 |
| **Integration test: CSV → PIT-38** — end-to-end test bez Doctrine (in-memory repos) | Kasia [QA] | Sprint 3 |
| **Agenci dostają brief o istniejących helpers** — prompt musi zawierać "użyj istniejącego X zamiast pisać swój" | Tech Lead | Od teraz |
| **Scope freeze per sprint** — nie pullujemy z przyszłych sprintów | Łukasz [risk] | Od teraz |
| **Jedno demo na koniec sprintu** — musi działać w przeglądarce, nie tylko w testach | Zofia [front] | Sprint 3 |

### Sprint 3 — plan

**Cel: działający demowalny produkt.** User otwiera przeglądarkę → loguje się → wgrywa CSV → widzi obliczenia → exportuje PIT-38 XML.

| Priorytet | Story | Owner |
|---|---|---|
| P0 | Symfony kernel bootstrap (routing, config, services) | Bartek + Marek |
| P0 | Doctrine setup + entity mappings (XML) | Marek |
| P0 | Database migrations (Postgres) | Marek |
| P0 | Auth: magic link login (Symfony Security) | Marek |
| P0 | Persistence: ImportSession, TransactionRepository | Ania |
| P0 | Wiring: Import → Calculate → Declaration flow | Marek |
| P1 | Dashboard: podsumowanie roku | Zofia + Paweł |
| P1 | PIT-38 preview w UI | Paweł |
| P1 | PIT-38 XML download (paid feature) | Paweł |
| P1 | Integration test: CSV → PIT-38 (in-memory) | Kasia |
| P2 | Refactor: CsvSanitizer trait | Ania |
| P2 | Duplicate detection | Ania |
| BLOCKED | XTB + mBank adaptery | Czeka na CSV |

### Velocity

Sprint 1+2 (połączone):
- **Planned:** ~22 stories
- **Done:** 16
- **Blocked:** 2 (XTB, mBank — external dependency)
- **Not started:** 4 (auth, persistence, dashboard, duplicates)
- **Velocity:** ~73% delivery rate

**Problem:** dostarczyliśmy dużo kodu, ale mało demowalnej wartości. Sprint 3 musi to odwrócić — mniej nowych features, więcej wiring + glue.

---

*Sprint Review & Retro: Fully Featured AI Agent Team, 2026-04-03.*
