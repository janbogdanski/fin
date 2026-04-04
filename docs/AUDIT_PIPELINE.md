# AI Audit Pipeline — Refinement Output

## Metadata

| | |
|---|---|
| Data | 2026-04-03 |
| Autorzy | Tech Lead, QA Lead (Kasia), Security Auditor (Michał P.), Tax Advisor (Tomasz), Performance Engineer (Aleksandra), Senior Dev (Marek), Legal Reviewer (Mec. Wiśniewska), Risk Manager (Łukasz), UX (Zofia), Audytor (Joanna), DDD Expert (Mariusz Gil) |
| Status | ZATWIERDZONE |
| Uwaga | Każdy nowy prompt agenta przechodzi: draft → prompt expert review → team review → zapis |

---

## 1. Pipeline Overview

```
EVERY COMMIT (CI, automated, seconds):
  [1] Code Review agent         — always
  [2] Security Audit agent      — if triggered (auth/input/secrets)
  [3] Performance Review agent  — if triggered (DB/query/cache)
  [4] QA Audit agent            — if triggered (new feature/AC)
  [5] Snapshot Tests (CI)       — PIT-38 XML golden comparison
  [6] Pentest Suite (CI)        — XSS/SQLi/CSV injection payloads
  [7] Fuzz Suite (CI)           — malformed CSV/input tests

EVERY SPRINT END (agent, ~36 min total):
  [8]  Legal Review             — disclaimers, narzędzie vs doradztwo
  [9]  Tax Advisor Review       — tax rules vs statute + regulatory map
  [10] Audit Trail Audit        — financial ops coverage
  [11] Architecture Audit       — Deptrac + BC boundaries + ADR drift

EVERY 2-3 SPRINTS (agent, ~45 min total):
  [12] GDPR Audit               — PII, retencja, erasure
  [13] Adversarial Review       — red team scenarios
  [14] User Story Replay        — ES completeness gap analysis

BEFORE RELEASE (agent, ~30 min total):
  [15] Compliance Audit         — regulamin vs implementation
  [16] UX Review                — user journey, disclaimers, a11y

ON DEMAND:
  [17] Pentest/Fuzz regeneration — new endpoints/adapters
```

---

## 2. Audit Catalog

| # | Audit | Type | Trigger | Input | Output | Czas | Tokeny | Priorytet |
|---|---|---|---|---|---|---|---|---|
| 1 | **Code Review** | Agent | Every commit | Changed files | Findings P0-P2 | 5-10 min | 20-40k | ACTIVE |
| 2 | **Security Audit** | Agent | Conditional/commit | Auth, input, secrets | OWASP findings | 5-10 min | 20-40k | ACTIVE |
| 3 | **Performance Review** | Agent | Conditional/commit | DB, query, cache | N+1, O(n²) findings | 5 min | 15-25k | ACTIVE |
| 4 | **QA Audit** | Agent | Conditional/commit | New features, AC | Edge cases, missing tests | 5-10 min | 20-30k | ACTIVE |
| 5 | **Legal Review** | Agent | Sprint end + template changes | Twig, regulamin, polityka prywatności | Legal risk findings | 5 min | 15k | **P1** |
| 6 | **Tax Advisor Review** | Agent | Sprint end + Domain changes | Domain services, golden dataset, teksty ustawy | Tax correctness findings + regulatory map | 8 min | 30k | **P1** |
| 7 | **GDPR Audit** | Agent | Co 2-3 sprinty + PII changes | Entities, mappings, loggers, cache | PII inventory, erasure gaps | 15 min | 40k | **P1** |
| 8 | **UX Review** | Agent | Co 2-3 sprinty + before release | Twig, Stimulus, ES user flow | UX issues, a11y gaps | 10 min | 25k | P2 |
| 9 | **Compliance Audit** | Agent | Before release + doc changes | Regulamin, landing, pricing vs features | Promise vs reality gaps | 5 min | 10k | **P1** |
| 10 | **Architecture Audit** | Agent | Sprint end + new Domain/App classes | Deptrac, imports, ADR-y | BC violations, ADR drift | 15 min | 35k | P2 |
| 11 | **Adversarial Review** | Agent | Co 2-3 sprinty + before release | Full system description | Attack scenarios z severity | 15 min | 50k | **P1** |
| 12 | **Simulated Pentest** | Agent (gen) + CI (exec) | Generacja raz + regen co 3-4 sprinty | Endpoints, forms, upload | PHPUnit security test suite | Gen: 15 min | Gen: 40k | **P1** |
| 13 | **Fuzzing** | Agent (gen) + CI (exec) | Generacja raz + regen per new adapter | CSV parsers, validators | PHPUnit fuzz test suite | Gen: 15 min | Gen: 35k | **P1** |
| 14 | **Audit Trail Audit** | Agent | Sprint end + new financial ops | Controllers, handlers, listeners | Operation × audit coverage matrix | 8 min | 20k | **P1** |
| 15 | **Snapshot Testing** | CI + Agent (on diff) | CI every commit, agent on diff | PIT-38 XML, audit report output | Pass/fail + diff review | Agent: 5 min | Agent: 15k | **P1** |
| 16 | **User Story Replay** | Agent | Sprint start (planning) + before release | EVENT_STORMING.md vs src/ | Completeness matrix per domain event | 15 min | 40k | P2 |

**Merged (nie osobne audyty):**
- **Regulatory Diff** → merged w Tax Advisor Review (#6). Regulatory map = artefakt w `docs/REGULATORY_MAP.md`.
- **Drift Detection** → merged w Architecture Audit (#10). ADR drift = część Architecture Audit.

---

## 3. Trigger Matrix

| Trigger | Audyty do uruchomienia |
|---|---|
| Every commit (CI) | Code Review, Security\*, Performance\*, QA\*, Snapshot Tests, Pentest Suite, Fuzz Suite |
| Every sprint end | Legal Review, Tax Advisor Review, Architecture Audit, Audit Trail Audit |
| Every 2-3 sprints | GDPR Audit, Adversarial Review, User Story Replay |
| Before release | Compliance Audit, UX Review, Adversarial Review (extra round), + wszystkie sprint-end |
| Template/doc change | Legal Review, Compliance Audit |
| Domain/TaxCalc change | Tax Advisor Review |
| PII-related change | GDPR Audit |
| New adapter added | Fuzz regen, QA Audit |
| Snapshot diff > 0 | Agent review snapshota |

\* = conditional trigger wg CLAUDE.md trigger matrix

---

## 4. Opisy audytów

### [5] Legal Review
**Autor:** Mec. Katarzyna Wiśniewska

Sprawdza czy żaden komunikat w UI nie przekracza granicy "narzędzie obliczeniowe" → "doradztwo podatkowe" (ustawa o doradztwie podatkowym, art. 81 = przestępstwo bez uprawnień). Skanuje Twig templates, regulamin, politykę prywatności. Szuka słów: "doradzamy", "zalecamy", "powinieneś" zamiast "obliczamy", "prezentujemy".

**Przykład finding (P0):** Przycisk "Rozlicz podatek" → powinno być "Wygeneruj deklarację PIT-38".

---

### [6] Tax Advisor Review + Regulatory Map
**Autor:** Tomasz Kędzierski, doradca podatkowy

Weryfikacja merytoryczna domain services vs tekst ustawy PIT, ordynacja podatkowa, UPO. Produkuje/aktualizuje `docs/REGULATORY_MAP.md` (tabela: artykuł → klasa/metoda → test → status).

**Przykład finding (P0):** DividendTaxService: overnight fee w CFD może nie być KUP wg części US. Potrzebny disclaimer lub switch.

---

### [7] GDPR Audit
**Autorzy:** Michał P. + Łukasz

Weryfikacja: jakie PII zbieramy, gdzie używane/cachowane/logowane, mechanizmy retencji i erasure, consent flow. Cross-check z ADR-012 (PII Encryption) i ADR-013 (Data Retention).

**Przykład finding (P1):** CachedExchangeRateProvider — klucz cache zawiera userId → można wywnioskować kiedy user robił transakcje. Minimalizacja naruszona.

---

### [9] Compliance Audit
**Autor:** Łukasz, risk-manager

Porównuje obietnice (regulamin, landing, pricing) z faktyczną implementacją.

**Przykład finding (P0):** Landing mówi "Obsługujemy XTB" — BLK-001 jest WAITING. Niedopuszczalne przed betą.

---

### [10] Architecture Audit (incl. Drift Detection)
**Autor:** Mariusz Gil, DDD expert + Marek

Deptrac + BC boundaries + ADR compliance. Każdy ADR ma key assertions → agent weryfikuje w kodzie.

**Przykład finding (P1):** ADR-015 mówi "magic link auth" — weryfikuje czy nie ma legacy password form.

---

### [11] Adversarial Review (Red Team)
**Autor:** Michał P.

Agent przyjmuje rolę złośliwego użytkownika. Szuka scenariuszy gdzie user traci pieniądze lub system umożliwia fraud podatkowy.

**Przykład finding (P0):** Ten sam CSV wgrany dwukrotnie pod inną nazwą → brak globalnej deduplikacji → podwójna strata → mniejszy podatek.

---

### [12] Simulated Pentest
**Autor:** Michał P.

Agent generuje PHPUnit suite z payloadami: XSS, SQLi, CSV injection, path traversal. Testy wchodzą do CI permanentnie.

**Przykład finding (P0):** `=HYPERLINK("http://evil.com","Click")` w CSV → przechodzi parsing → pojawia się jako link w UI. CSV injection.

---

### [13] Fuzzing
**Autor:** Kasia

Agent generuje PHPUnit suite z malformed inputs dla CSV parserów: losowe bajty, null bytes, Unicode control chars, 100k wierszy, notacja naukowa w kwotach.

**Przykład finding (P1):** Kwota "1,234.56" (przecinek tysięcy) parsuje się jako "1". User traci 1233.56 w obliczeniu kosztu.

---

### [14] Audit Trail Audit
**Autor:** Joanna Makowska, biegły rewident

Weryfikuje czy każda operacja finansowa ma wpis w audit trail. Matryca: operacja × coverage.

**Przykład finding (P1):** PriorYearLossController::store() nie loguje kto/kiedy/ile. Przy kontroli US nie można udowodnić że user sam to wpisał.

---

### [15] Snapshot Testing
**Autor:** Marek

PIT-38 XML i audit report porównywane bajt po bajcie z zatwierdzonym wzorcem. Zmiana = explicit approve z agentem.

**Przykład finding (P0):** Refaktor zmienił kolejność atrybutów w XML → e-Deklaracje odrzuca → user nie może złożyć PIT-38.

---

### [8] UX Review
**Autor:** Zofia

User journey z perspektywy Kowalskiego z CSV z IBKR. Czy rozumie co robi? Czy disclaimer widoczny przed generacją PIT-38? WCAG 2.1 AA.

**Przykład finding (P1):** Strona importu nie mówi które formaty są obsługiwane. User dostaje "Nierozpoznany format" bez podpowiedzi.

---

### [16] User Story Replay
**Autor:** Zofia + Paweł

Event Storming → matryca completeness: każdy domain event ma status IMPLEMENTED / PARTIALLY / MISSING.

**Przykład finding (P2):** Event #24 (Import z PIT-8C) — brak implementacji, brak nawet TODO.

---

## 5. Cost Budget

### Per-commit (CI, zero agent cost):
- Snapshot tests + Pentest suite + Fuzz suite: **<30 sekund, 0 tokenów**

### Per-sprint (agent audits, sprint end):
| Audit | Czas | Tokeny |
|---|---|---|
| Legal Review | 5 min | 15k |
| Tax Advisor Review | 8 min | 30k |
| Architecture Audit | 15 min | 35k |
| Audit Trail Audit | 8 min | 20k |
| **RAZEM** | **~36 min** | **~100k** |

### Per 2-3 sprints (dodatkowe):
| Audit | Czas | Tokeny |
|---|---|---|
| GDPR Audit | 15 min | 40k |
| Adversarial Review | 15 min | 50k |
| User Story Replay | 15 min | 40k |
| **RAZEM** | **~45 min** | **~130k** |

### Pre-release (jednorazowe):
| Audit | Czas | Tokeny |
|---|---|---|
| Compliance Audit | 5 min | 10k |
| UX Review | 10 min | 25k |
| Adversarial Review extra | 15 min | 50k |
| **RAZEM** | **~30 min** | **~85k** |

**Worst case per sprint: <90 minut agentowego czasu, <315k tokenów. Mieści się w budżecie.**

---

## 6. Implementation Roadmap

| Sprint | Zadanie | Effort |
|---|---|---|
| 12 | Prompt: Legal Review (#5) + Tax Advisor Review (#6) | 2h |
| 12 | Snapshot Testing (#15) — generacja golden XML snapshots | 1h |
| 12 | Stworzenie `docs/REGULATORY_MAP.md` (jednorazowe) | 2h |
| 13 | Prompt: Audit Trail Audit (#14) | 1h |
| 13 | Simulated Pentest (#12) — generacja PHPUnit security suite | 2h |
| 13 | Fuzzing (#13) — generacja PHPUnit fuzz suite | 2h |
| 14 | Prompt: GDPR Audit (#7) + Adversarial Review (#11) | 3h |
| 14 | Prompt: Compliance Audit (#9) | 1h |
| 15 | Prompt: Architecture Audit (#10, incl. Drift) | 1.5h |
| 15 | Prompt: UX Review (#8) | 1h |
| 16 | Prompt: User Story Replay (#16) | 1.5h |

**Łącznie: ~18h rozłożone na 5 sprintów.**

> **Uwaga:** Każdy prompt przed użyciem → prompt expert review → team review → zapis.

---

## 7. Open Items

| Item | Status | Impact |
|---|---|---|
| BLK-003: Opinia prawna (narzędzie vs doradztwo) | WAITING | Legal Review to interim safeguard, nie zastępuje prawdziwej opinii |
| BLK-004: DPIA (GDPR Art. 35) | WAITING | GDPR Audit to proxy, nie zastępuje formalnego DPIA |
| `docs/REGULATORY_MAP.md` — stworzenie | TODO | Tax Advisor Review nie może produkować diff bez baseline |
| Weryfikacja: ADR-y dostępne w `docs/adr/` | VERIFY | Architecture Audit potrzebuje plików ADR |
| Weryfikacja: regulamin + landing page w repo | VERIFY | Compliance Audit + Legal Review potrzebują tych plików |

---

*Zatwierdzone przez zespół 2026-04-03. Prompty agentów: draft → prompt expert review → team review → zapis.*
