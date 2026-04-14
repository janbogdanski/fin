# RELEASE READINESS CHECKLIST — TaxPilot

## Metadata

| | |
|---|---|
| Status | DRAFT |
| Purpose | Checklista operacyjna dla `closed beta` i `public production cutover` |
| Use With | `docs/PROD_BLOCKERS.md`, `docs/PROD_EXECUTION_PLAN.md`, `docs/agents/prod-readiness-orchestrator-agent-prompt.md` |
| Rule | Każdy punkt musi mieć dowód lub jawne odstępstwo zaakceptowane przez Tech Leada |

## How To Use

1. Uzupełnij status i dowód przy każdym punkcie.
2. Otwórz lub zaktualizuj blocker, jeśli punkt jest czerwony.
3. Uruchom `Prod Readiness Orchestrator` przed decyzją GO / NO-GO.
4. Zapisz wynik decyzji na dole dokumentu.

## Status Legend

| Status | Meaning |
|---|---|
| TODO | Punkt niezweryfikowany |
| DONE | Punkt zweryfikowany dowodem |
| WAIVED | Świadomie odroczone z akceptacją ryzyka |

## Closed Beta Gate

| ID | Check | Owner | Status | Evidence |
|---|---|---|---|---|
| BETA-001 | Scope v1 i lista wspieranych brokerów są zamrożone | Product Owner | IN PROGRESS | `docs/SCOPE_V1.md` DRAFT — czeka na potwierdzenie PO |
| BETA-002 | `docs/PROD_BLOCKERS.md` jest aktualne i nie ma otwartych blockerów beta | Tech Lead | TODO | |
| BETA-003 | RC/tag przechodzi pełne CI: unit, integration, golden, property, contract, security, E2E, audit | QA Lead | TODO | Wymaga: wypchnięcie `v0.1.0-rc.1` i zielony `release.yml` |
| BETA-004 | PIT-38 XML jest walidowany oficjalnym XSD MF | QA Lead | DONE | `PIT38OfficialXsdValidationTest` (5 scenariuszy), `tests/Fixtures/official_xsd/schemat.xsd`; BETA-BLK-005 CLOSED 2026-04-14 |
| BETA-005 | Opinia prawna dla granicy `narzędzie` vs `PIT-38 XML` jest zamknięta | Legal Reviewer | TODO | |
| BETA-006 | DPIA jest ukończone i działania wynikające są zapisane | Security Auditor | TODO | |
| BETA-007 | Deploy z taga na staging-like środowisko przeszedł end-to-end | DevOps | TODO | |
| BETA-008 | Smoke test po deployu jest zielony | DevOps | TODO | |
| BETA-009 | Monitoring minimum działa: Sentry, uptime, health, alert test | DevOps | IN PROGRESS | `/health` endpoint zaimplementowany (`HealthController`, 4 testy green); brak dowodu aktywacji Sentry i UptimeRobot — wymagana konfiguracja `.env.local` na MyDevil |
| BETA-010 | Runbook release/incydent/support istnieje i jest używalny | Tech Writer | IN PROGRESS | `docs/RUNBOOK.md` zaktualizowany: 2 delivery paths, rollback via tags, feedback loop; contact points i rehearsal TBD |
| BETA-011 | Feedback channel, triage owner i rytm review dla bety są ustalone | Product Owner | IN PROGRESS | `docs/BETA_FEEDBACK.md` DRAFT — klasyfikacja P0-P3, rytm review, exit criteria; kanał i owner TBD (czeka na decyzję PO) |
| BETA-012 | Decyzja `GO / NO-GO` dla closed beta jest zapisana | Tech Lead | TODO | |

## Public Production Gate

| ID | Check | Owner | Status | Evidence |
|---|---|---|---|---|
| PROD-001 | Closed beta została podsumowana, a launch blockers są jawnie zamknięte lub odcięte ze scope | Tech Lead | TODO | |
| PROD-002 | Nie ma otwartych P0/P1 z security, legal, tax, QA i correctness | Tech Lead | TODO | |
| PROD-003 | Rollback drill został wykonany na prod-like ścieżce | DevOps | TODO | |
| PROD-004 | Backup restore drill został wykonany i zweryfikowany | DevOps | TODO | |
| PROD-005 | Alerting został sprawdzony testem, a owner incydentów jest jawny | DevOps | TODO | |
| PROD-006 | Support owner i ścieżka eskalacji są znane całemu zespołowi | Product Owner | TODO | |
| PROD-007 | Publiczne obietnice w pricing/landing/docs są zgodne z faktycznym scope | Compliance | TODO | |
| PROD-008 | Release tag, CI, deploy i smoke dla finalnego cutover są zielone | DevOps | TODO | |
| PROD-009 | Decyzja `GO / NO-GO` dla public launch jest zapisana | Tech Lead | TODO | |

## Same-Day Cutover Checks

| ID | Check | Owner | Status | Evidence |
|---|---|---|---|---|
| CUTOVER-001 | DNS / Cloudflare / SSL są poprawnie ustawione | DevOps | TODO | |
| CUTOVER-002 | `APP_ENV=prod`, sekrety i połączenia do DB/Redis/Mailer są poprawne | DevOps | TODO | |
| CUTOVER-003 | Migrations wykonały się bez błędu | DevOps | TODO | |
| CUTOVER-004 | Cache warmup przeszedł | DevOps | TODO | |
| CUTOVER-005 | HTTP smoke zwraca oczekiwany status | DevOps | TODO | |
| CUTOVER-006 | `/health` zwraca stan zgodny z oczekiwaniem | DevOps | TODO | |
| CUTOVER-007 | Sentry / uptime / podstawowe alerty widzą nową wersję | DevOps | TODO | |
| CUTOVER-008 | Owner launch window potwierdził gotowość do obserwacji po starcie | Tech Lead | TODO | |

## Decision Log

| Date | Gate | Decision | Why | Evidence | Decider |
|---|---|---|---|---|---|
| YYYY-MM-DD | closed beta / public prod | GO / WARUNKOWE GO / NO-GO | | | |
