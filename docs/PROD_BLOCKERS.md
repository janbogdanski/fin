# PROD BLOCKERS — TaxPilot

## Metadata

| | |
|---|---|
| Status | DRAFT |
| Purpose | Operacyjny rejestr blockerów do bety i publicznego production launch |
| Source of truth | `docs/PROD_EXECUTION_PLAN.md`, `docs/REVIEW_CONSOLIDATED.md`, `docs/TEST_METRICS.md`, `docs/DEPLOY.md`, `.github/workflows/*.yml` |
| Rule | Blocker zamyka się dopiero po istnieniu dowodu, nie po deklaracji |

## Status Legend

| Status | Meaning |
|---|---|
| OPEN | Nie ma jeszcze dowodu zamknięcia |
| IN PROGRESS | Owner pracuje, ale gate nie jest jeszcze spełniony |
| READY FOR VERIFY | Praca skończona, czeka na review lub dowód wykonania |
| CLOSED | Gate spełniony, dowód istnieje |
| EXTERNAL | Zależność poza repo lub poza zespołem delivery |

## Policy

- Beta nie startuje z żadnym `OPEN` blockerem w sekcji Beta Gate.
- Public production launch nie startuje z żadnym `OPEN` blockerem w sekcji Public Gate.
- `NO-GO` jest domyślną decyzją, dopóki dowody nie są jawne.
- Każdy blocker ma jednego ownera i jedno najbliższe następne działanie.

## Beta Gate Blockers

| ID | Area | Blocker | Why Blocking | Owner | Status | Evidence Now | Exit Criteria | Next Action |
|---|---|---|---|---|---|---|---|---|
| BETA-BLK-001 | Scope | Scope v1 i lista wspieranych brokerów nie są zamrożone w jednym artefakcie | Bez tego zespół nie odróżni launch scope od backlogu i będzie dokładał przypadkowe rzeczy | Product Owner | OPEN | Jest plan wykonawczy, ale brak jawnej listy `supported at launch` | Istnieje zaakceptowana lista: brokerzy, flow, limity i to, czego nie wspieramy w v1 | Spisać `supported at launch` i `explicitly out of scope` |
| BETA-BLK-002 | Release Discipline | Brak potwierdzonej strategii `release branch/tag -> CI -> deploy` na właściwej linii releasowej | Zielony `main` nie jest tym samym co zielony kandydat do releasu | Tech Lead | OPEN | Deploy workflow istnieje, ale brak udokumentowanego i sprawdzonego release line | Jest release branch/tag policy, a CI i deploy są sprawdzone na tagu RC | Spisać i przetestować ścieżkę RC |
| BETA-BLK-003 | Legal | Brak zamkniętej opinii prawnej o granicy `narzędzie` vs `sporządzanie PIT-38 XML` | To jest jawny blocker produktu i modelu sprzedaży | Legal Reviewer | EXTERNAL | Review konsolidowany wskazuje to jako otwarte | Istnieje pisemna decyzja prawna: GO / ograniczony GO / pivot | Zamówić opinię i zapisać wynik jako artefakt |
| BETA-BLK-004 | Compliance | Brak zamkniętego DPIA | Start przetwarzania bez DPIA to ryzyko compliance | Security Auditor | EXTERNAL | Review konsolidowany wskazuje to jako otwarte | DPIA ukończone, ryzyka zaakceptowane, działania zapisane | Zlecić DPIA i wpisać działania następcze |
| BETA-BLK-005 | Correctness | PIT-38 XML nie jest walidowany oficjalnym XSD MF | Obecne minimum strukturalne nie daje wystarczającej pewności eksportu | QA Lead | OPEN | `tests/Fixtures/pit38_minimal.xsd` jest jawnie oznaczony jako nieoficjalny | CI waliduje XML na oficjalnym XSD MF | Zastąpić minimalny XSD oficjalnym i dodać gate do releasu |
| BETA-BLK-006 | Quality | Brak potwierdzonego stanu `all critical suites green` na releasowanej wersji | Dokumenty i commity pokazują postęp, ale brak świeżego dowodu na całej ścieżce releasowej | QA Lead | OPEN | CI ma wszystkie suite'y, ale nie ma tu dowodu wykonanego RC | CI dla RC/taga przechodzi: unit, integration, golden, property, contract, security, E2E, audit | Uruchomić RC i zapisać wynik |
| BETA-BLK-007 | Delivery | Brak sprawdzonego deployu `from tag` na staging-like środowisko | Sam workflow nie wystarcza; potrzebny dowód end-to-end | DevOps | OPEN | `deploy.yml` i `docs/DEPLOY.md` istnieją | Deploy z taga przechodzi: install, assets, migrate, warmup, smoke | Wykonać pełny staging deploy rehearsal |
| BETA-BLK-008 | Observability | Monitoring i alerting są opisane, ale brak dowodu działania w środowisku docelowym | Bez tego pierwsze incydenty wyjdą od użytkowników | DevOps | OPEN | ADR-009 opisuje Sentry/Uptime/health, brak dowodu aktywacji | Sentry, uptime, `/health`, alert test i owner incydentów są gotowe | Skonfigurować minimum operacyjne i wykonać test alertu |
| BETA-BLK-009 | Operations | Runbook release/incydent/support nie istnieje jako jawny artefakt operacyjny | W incydencie zespół będzie improwizował | Tech Writer | READY FOR VERIFY | Istnieje `docs/RUNBOOK.md`, ale wymaga jeszcze uzupełnienia ownerów i praktycznego użycia | Istnieje runbook z deploy, rollback, restore, incident triage, contact points i został użyty w rehearsal | Uzupełnić ownerów, wykonać rehearsal i poprawić luki |
| BETA-BLK-010 | Feedback Loop | Brak jawnej ścieżki zbierania feedbacku z bety i triage P0/P1 | Beta bez feedback pipeline to tylko miękki launch bez kontroli | Product Owner | OPEN | Plan mówi o feedback loop, brak checklisty wykonawczej | Jest kanał feedbacku, owner, SLA triage i ścieżka decyzji beta/blocker/backlog | Ustalić kanał i rytm review |

## Public Gate Blockers

| ID | Area | Blocker | Why Blocking | Owner | Status | Evidence Now | Exit Criteria | Next Action |
|---|---|---|---|---|---|---|---|---|
| PROD-BLK-001 | Reliability | Rollback drill nie został sprawdzony praktycznie | Brak rollbacku oznacza ryzyko utknięcia po złym deployu | DevOps | OPEN | `docs/DEPLOY.md` mówi o ręcznym re-deployu poprzedniego taga, ale brak drill | Udokumentowany rollback został wykonany i zweryfikowany | Wykonać rollback rehearsal na staging/prod-like |
| PROD-BLK-002 | Reliability | Backup restore drill nie został sprawdzony praktycznie | Backup bez restore to fałszywe bezpieczeństwo | DevOps | OPEN | ADR-009 opisuje backup cron, brak dowodu restore | Odtworzenie backupu do osobnej instancji zostało wykonane | Wykonać i zapisać restore drill |
| PROD-BLK-003 | Beta Evidence | Brak zamkniętej retrospektywy po closed beta z listą launch blockers | Public launch bez lekcji z bety to ignorowanie realnego ruchu użytkowników | Tech Lead | OPEN | Plan zakłada closed beta, brak artefaktu wynikowego | Istnieje raport beta: incydenty, decyzje, cięcia scope, poprawki launch-blocking | Zamknąć beta retro i decyzję GO/NO-GO |
| PROD-BLK-004 | Support | Brak jawnego ownera supportu i incydentów po starcie | W pierwszych dniach po launchu trzeba wiedzieć kto odbiera problem i kto decyduje | Product Owner | OPEN | Brak dedykowanego dokumentu operacyjnego | Jest owner supportu, godziny reakcji i ścieżka eskalacji | Dopisać do runbooka i checklisty |
| PROD-BLK-005 | Risk | Pozostałe P0/P1 z security, legal, tax lub QA nie są jawnie zamknięte albo zaakceptowane | Public prod nie może polegać na „chyba już dobrze” | Tech Lead | OPEN | Istnieją wcześniejsze review findingi, brak jednego zamknięcia releasowego | Jest aktualna decyzja GO oparta o zamknięte lub zaakceptowane ryzyka | Odpalić pełny orchestrator audit przed cutover |

## Current Sequence

1. Zamknąć `BETA-BLK-001` i `BETA-BLK-002`, bo bez scope freeze i release line reszta będzie się rozjeżdżać.
2. Równolegle ruszyć `BETA-BLK-003`, `BETA-BLK-004` i `BETA-BLK-005`, bo to najwolniejsze lub najbardziej krytyczne zależności.
3. Potem dowieźć `BETA-BLK-006` do `BETA-BLK-010` na releasowanej linii.
4. Po closed beta zamknąć `PROD-BLK-*` przed publicznym launch.

## Decision Record

| Date | Decision | Scope | Evidence | Decider |
|---|---|---|---|---|
| YYYY-MM-DD | NO-GO / WARUNKOWE GO / GO | beta / public prod | link do checklisty, CI, review, runbook | Tech Lead |
