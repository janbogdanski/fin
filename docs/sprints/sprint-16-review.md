# Sprint 16 Review — TaxPilot

- **Data:** 2026-04-05
- **Sprint:** 16
- **Status:** DONE

---

## Goal

> QA Coverage Audit — zamknięcie wszystkich luk pokrycia testowego wykrytych w statycznym audycie

**Verdict:** Goal achieved. 13 backlog items (P2-085, P2-117..128) closed. Łączna liczba testów wzrosła z ~2071 do 2084 (unit + integration). Fuzz suite rozszerzona o DegiroAccountStatementAdapter.

---

## AC Summary

| # | Acceptance Criterion | Status |
|---|---|---|
| 1 | AnonymizeUserHandler — unit test: user not found + transactional + idempotency | DONE |
| 2 | LossFormValidator — unit test: 9 scenariuszy parseAmount/parseCategory | DONE |
| 3 | PricingConsentController — integration test: 4 ścieżki | DONE |
| 4 | UserSubscriptionExtension — unit test: Free/Standard/Pro/non-SecurityUser | DONE |
| 5 | UserProvider — unit test: supportsClass + loadByIdentifier + refreshUser | DONE |
| 6 | EncryptionKeyInitializer — unit test: main request sets key, sub-request nie | DONE |
| 7 | GetTaxSummaryAdapter — unit test: delegacja + multi-year (2023, 2024) | DONE |
| 8 | DashboardControllerWebTest — zastąpienie luźnej asercji str_contains('0') | DONE |
| 9 | MagicLinkAuthenticator — unit test: migrate(true) explicite assertowane | DONE |
| 10 | AnonymizeUserHandler — idempotency: drugi call = DomainException | DONE |
| 11 | AnnualTaxCalculationService — multi-loss clamping: 3 straty, gain < suma | DONE |
| 12 | CsvParserFuzzTest — DegiroAccountStatementAdapter: 6 nowych scenariuszy | DONE |
| 13 | User Story Replay agent (#16) — prompt z macierzą 118 zdarzeń ES | DONE |

---

## Deliverables

### Nowe pliki testowe

| Plik | Testy | Typ |
|------|-------|-----|
| `tests/Unit/Identity/Application/AnonymizeUserHandlerTest.php` | 4 | Unit |
| `tests/Unit/Identity/Infrastructure/Security/MagicLinkAuthenticatorTest.php` | 3 | Unit |
| `tests/Unit/Identity/Infrastructure/Security/UserProviderTest.php` | 4 | Unit |
| `tests/Unit/Shared/Infrastructure/Doctrine/EncryptionKeyInitializerTest.php` | 2 | Unit |
| `tests/Unit/Declaration/Infrastructure/Adapter/GetTaxSummaryAdapterTest.php` | 2 | Unit |
| `tests/Unit/TaxCalc/Domain/LossFormValidatorTest.php` | 9 | Unit |
| `tests/Unit/Shared/Infrastructure/Twig/UserSubscriptionExtensionTest.php` | 4 | Unit |
| `tests/Integration/PricingConsentControllerWebTest.php` | 4 | Integration |

### Zmodyfikowane pliki

| Plik | Zmiana |
|------|--------|
| `tests/Integration/DashboardControllerWebTest.php` | Silniejsze asercje semantyczne |
| `tests/Fuzz/CsvParserFuzzTest.php` | +6 testów DegiroAccountStatementAdapter |
| `tests/Unit/TaxCalc/Application/AnnualTaxCalculationServiceTest.php` | +1 test multi-loss clamping |

### Agent prompts

| Plik | Opis |
|------|------|
| `docs/agents/user-story-replay-agent-prompt.md` | Agent #16 — walidacja przepływów ES, 118 zdarzeń, 4 severity |

---

## Test Suite State (post-sprint)

| Suite | Testy | Uwagi |
|-------|-------|-------|
| Unit + Integration (domyślny) | 2084 | 40 pre-existing errors — FK violations w Contract suite |
| Fuzz (`--group fuzz`) | 30 | Green |
| Canary (`--group canary`) | external | Nightly CI, NBP API |

**Pre-existing errors:** 40 FK violations w `tests/Contract/Repository/` i `ImportControllerDedupTest` — nie wprowadzone przez Sprint 16. Wymagają osobnego sprintu (seed data / test isolation).

---

## Bugs wykryte podczas sprintu

| ID | Opis | Fix |
|----|------|-----|
| — | `transactional()` double-call w AnonymizeUserHandlerTest — setUp + test oba konfigurowały mock | Usunięto z setUp, dodano `stubTransactional()` helper |
| — | `EncryptionKeyInitializerTest` false-green (`expectNotToPerformAssertions()`) | Zastąpiono realnym `convertToDatabaseValue()` + string assertions |
| — | `PricingConsentControllerWebTest` słaba asercja (brak check `/login`) | Dodano `assertStringContainsString('/login', $location)` |

---

## Deferred / Backlog

| ID | Opis | Powód |
|----|------|-------|
| P2-045 | Golden dataset expansion | BLOCKED — potrzebne realne pliki CSV od usera |
| P2-088 / P3-009 | Artykuł XTB PIT-38 | BLOCKED — brak XtbAdapter |
| P2-103 | Content bug XTB CSV claim | BLOCKED — brak XtbAdapter |
| P2-033 | SQL aggregation performance | DEFERRED — relevantne przy >5000 pozycji/user |
| P2-034 | StreamedResponse dla CSV export | DEFERRED — relevantne przy >10MB export |
| P2-036 | NBP pre-warming | DEFERRED — relevantne przy >10k users |
| P3-016 | i18n UA/EN | Future — sezon PIT 2027 |

---

## Sprint 17 Focus (proposed)

| Priorytet | Item | Uzasadnienie |
|-----------|------|--------------|
| **P0** | Fix 40 pre-existing FK violations w Contract suite | Błędy maskują realne problemy |
| **P1** | Production deploy — infrastruktura + deploy pipeline | Odraczane od Sprint 12 |
| **P2** | Contract test data seeding — fixtures lub test mothers dla DB state | Prerequisite dla contract suite |
| **P2** | Mutation testing refresh (MSI %) po nowych testach | Sprint 11: 82%, Sprint 16 dodał 12 testów |

**Sprint 17 goal (proposed):** Production-ready deploy pipeline + Contract suite zielona.
