# Sprint 11 Review — TaxPilot

- **Data:** 2026-04-03
- **Sprint:** 11
- **Status:** DONE

---

## Goal

> E2E tests, MSI 76% → 82%, full CI pipeline hardening

**Verdict:** Goal achieved. MSI target exceeded (82% vs 80% planned). CI pipeline hardened across all 3 stages. E2E suite shipped with 6 flows.

---

## AC Summary

| # | Acceptance Criterion | Status |
|---|---|---|
| 1 | E2E tests: 6 flows (dashboard import, declaration preview, loss management, magic link, landing nav, public pages navigation) — Panther-based | DONE |
| 2 | MSI 82% (`infection.json5`: `minMsi=82`, `minCoveredMsi=90`) — 13 mutation test files added | DONE |
| 3 | CI: Redis in all 3 stages, repository-contract + e2e suites in CI, unified `ENCRYPTION_KEY`, `permissions:contents:read`, removed `--min-msi` CLI override | DONE |
| 4 | Doctrine contract tests: `DoctrineImportStorageTest` + `DoctrinePriorYearLossCrudTest` (base classes extend `KernelTestCase`) | DONE |
| 5 | Bug fix: `DoctrineImportStorageAdapter` was ignoring `$brokerId` parameter, using `$tx->broker` instead (caused wrong broker count) | DONE |
| 6 | Docs: `TEST_METRICS.md`, `AUDIT_PIPELINE.md` (16 audit types), `CONTENT_STANDARDS.md`, `CONTENT_BRIEF_TEMPLATE.md`, `content-writer-agent-prompt.draft.md` | DONE |

---

## Test Metrics

| Metric | Sprint 10 | Sprint 11 | Target Beta (02.2027) |
|---|---|---|---|
| Total tests | ~951 | **1009** | >= 1 200 |
| MSI (Infection) | 76% | **82%** | >= 80% |
| E2E flows | 0 | **6** | >= 8 |
| Repository-contract tests | 0 | **58** (4 files) | — |
| PHPStan errors | 0 | 0 | 0 |
| ECS violations | 0 | 0 | 0 |
| Deptrac violations | 0 | 0 | 0 |

### Active test suites

`unit` · `integration` · `golden-dataset` (8 scenarios) · `property` · `contract` · `repository-contract` (4 files, 58 tests) · `e2e` (6 flows) · `canary` · `chaos`

---

## Review Pipeline Summary

### Security Auditor

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| — | P1 | `ENCRYPTION_KEY` not unified across CI stages — could cause decrypt failures in stage 3 | Fixed: unified key injected in all stages |
| — | P1 | CI workflow lacked `permissions:contents:read` — could allow unintended write access | Fixed: added permission + `# CI-ONLY` comments |
| P2-046 | P2 | `.env.test` placeholder key (`changeme`) not flagged | Backlogged |
| P2-047 | P2 | Referral code predictability (sequential or guessable) | Backlogged |

### Code Reviewer

- Redis service added to all 3 CI stages (was missing in stages 1 and 3).
- `repository-contract` and `e2e` suites wired into CI.
- Contract test for `DIVIDEND` transaction type exclusion from capital gains calculation added.

### QA Lead

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| P1-049 | P1 | Doctrine contract tests missing — no guarantee adapters respect domain contracts | Fixed same sprint: `DoctrineImportStorageTest` + `DoctrinePriorYearLossCrudTest` |
| P1-050 | P1 | 5 output port contract tests missing (e.g., `TaxReportRepository`, `ExchangeRateProvider`) | Deferred to Sprint 12 |
| P1-051 | P1 | CSV fuzzing suite absent | Deferred to Sprint 12 |
| P1-052 | P1 | `date('Y')` flakiness in golden dataset assertions (year boundary) | Deferred to Sprint 12 |
| P2-099..102 | P2 | Various test coverage gaps (XML schema validation, auth boundary generator, PII leak detection, disclaimer regression) | Backlogged |

---

## Retrospective

### What Went Well

- Full review pipeline executed in parallel (Security + Code + QA) — zero skips.
- Doctrine contract test gap caught by QA and fixed within the same sprint (P1-049).
- MSI exceeded target: **82%** vs **80%** planned.
- `DoctrineImportStorageAdapter` broker count bug found and fixed via contract tests — validated that the new test layer has immediate ROI.
- CI is now the strongest it has been: Redis everywhere, proper permissions, no manual MSI overrides.

### What to Improve

- P1-050 (5 output port contract tests) deferred — contract coverage remains incomplete. Port contracts are the highest-value safety net before wiring real flows; this must not slip again.
- P1-052 (`date('Y')` flakiness) not resolved — creates risk of false negatives at year boundary in CI runs. Simple fix, no excuse to carry it into Sprint 13.
- Chaos test count did not grow this sprint — suite exists but coverage is static.

---

## Sprint 12 Focus

| Priority | Item | Source |
|---|---|---|
| P1 | Output port contract tests (5 missing) | P1-050 |
| P1 | CSV fuzzing suite | P1-051 |
| P1 | Fix `date('Y')` flakiness in golden dataset | P1-052 |
| P2 | PIT-38 XML schema validation (XSD gate in CI) | P2-061 |
| P2 | Approval/snapshot testing on generated XML | P2-062 |
| P2 | Golden dataset expansion (+4 scenarios → 12 total) | P2-063..066 |
| P2 | Auth boundary regression generator | P2-067 |
| P2 | Legal advisor agent prompt (draft → reviewed → saved) | P2-073 |
| P2 | Tax advisor agent prompt (draft → reviewed → saved) | P2-074 |

**Sprint 12 goal (proposed):** Output port contract coverage complete, XML schema validation in CI, golden dataset ≥ 12 scenarios.
