# RUNBOOK — TaxPilot v1

## Metadata

| | |
|---|---|
| Status | DRAFT |
| Purpose | Operacyjny runbook dla deployu, smoke, rollbacku, restore i pierwszej reakcji incydentowej |
| Runtime | MyDevil + Cloudflare |
| Source of truth | `docs/DEPLOY.md`, `.github/workflows/deploy.yml`, `docs/adr/ADR-009-docker-ecs-fargate.md` |

## Scope

Ten runbook dotyczy:

- closed beta
- public production cutover
- pierwszych incydentów po deployu
- rollback i restore drill

Nie zastępuje checklisty readiness. Używaj razem z:

- `docs/RELEASE_READINESS_CHECKLIST.md`
- `docs/PROD_BLOCKERS.md`
- `docs/PROD_EXECUTION_PLAN.md`

## Current Delivery Path

Obecnie zaimplementowana ścieżka deployu to:

1. zmiana trafia na `main`
2. GitHub Actions uruchamia `CI`
3. po zielonym `CI` uruchamia się `deploy.yml`
4. deploy wykonuje:
   - `composer install --no-dev`
   - build Tailwind
   - zapis `.env.local` na serwerze
   - `rsync`
   - `doctrine:migrations:migrate`
   - `cache:warmup`
   - smoke test HTTP

## Target Hardening

Przed public prod path powinien być dodatkowo utwardzony o:

- jawny release candidate tag lub release branch discipline
- staging/prod-like deploy rehearsal
- rollback drill
- restore drill
- alert test

## Roles During Release Window

| Role | Responsibility |
|---|---|
| Tech Lead | Finalne GO / NO-GO, scope freeze, decyzje przy blockerach |
| DevOps | Deploy, smoke, rollback, monitoring, restore |
| QA Lead | Weryfikacja quality gate'ów i krytycznych flow |
| Security Auditor | Ocena ryzyka przy security/compliance blockers |
| Product Owner | Decyzja o cięciu scope i komunikacja launch scope |

## Pre-Deploy Checks

Przed cutover upewnij się, że:

- checklista readiness jest aktualna
- blocker register jest aktualny
- finalny scope release jest zamknięty
- nie ma otwartych P0/P1 blockers
- release owner jest wyznaczony
- owner monitoringu i incydentów jest wyznaczony

## Deploy Procedure

### 1. Release approval

- Tech Lead potwierdza `GO`
- Product Owner potwierdza scope
- QA Lead potwierdza zielone gate'y
- DevOps potwierdza gotowość środowiska

### 2. Trigger deployment

Current path:
- merge lub push releasowanej zmiany do `main`
- obserwuj `CI`
- po sukcesie obserwuj `Deploy to MyDevil`

### 3. Verify deployment

Po deployu sprawdź:

- migrations zakończone sukcesem
- cache warmup zakończony sukcesem
- smoke test HTTP przeszedł
- homepage lub login odpowiada
- jeśli istnieje endpoint health, zwraca oczekiwany stan
- jeśli monitoring błędów jest skonfigurowany, nie pokazuje spike'a 5xx lub wyjątków boot-time

## Smoke Checklist

| Check | Expected result |
|---|---|
| HTTP smoke | `200` lub `302` zgodnie z `deploy.yml` |
| Health endpoint | jeśli istnieje, zwraca stan zgodny z oczekiwaniem |
| Login flow entry | strona logowania renderuje się poprawnie |
| Public landing/pricing/legal pages | odpowiadają bez 5xx |
| Monitoring | jeśli skonfigurowany, nowy deploy jest widoczny w narzędziach operacyjnych |

## Rollback Procedure

### Trigger rollback when

- smoke test nie przechodzi
- po deployu jest powtarzalny 5xx
- krytyczny flow użytkownika jest zepsuty
- błąd migracji lub błąd boot-time blokuje ruch

### Current rollback model

Obecny model jest manualny:

- wróć do poprzedniego znanego dobrego release
- wykonaj redeploy poprzedniej wersji
- powtórz smoke

### Notes

- rollback musi być sprawdzony praktycznie przed public prod
- jeśli migracja jest nierozłączna wstecz, rollback wymaga jawnej procedury DB
- nie zakładaj, że rollback zadziała tylko dlatego, że istnieje poprzedni commit

## Backup And Restore

### Backup expectation

- regularny dump DB
- kopia poza hostem produkcyjnym
- szyfrowanie backupu

### Restore drill minimum

Restore drill jest zaliczony dopiero gdy:

1. wybrano realny backup
2. odtworzono go do osobnej instancji
3. aplikacja mogła odczytać dane
4. wynik został zapisany jako dowód

## Incident Triage

### Severity model

| Severity | Meaning | Initial response |
|---|---|---|
| P0 | Aplikacja niedostępna, błędne obliczenia, broken payments, data risk | natychmiastowy stop release / rollback |
| P1 | Krytyczny flow części użytkowników nie działa | hotfix lub ograniczenie scope |
| P2 | Problem nieblokujący launchu, z obejściem | backlog po stabilizacji |

### First questions

- Czy problem dotyczy wszystkich userów czy wybranego flow?
- Czy problem zaczął się po ostatnim deployu?
- Czy dotyczy danych, poprawności podatkowej lub bezpieczeństwa?
- Czy rollback jest bezpieczniejszy niż hotfix?

## Post-Deploy Observation Window

Przez pierwsze 30-60 minut po deployu obserwuj:

- error rate
- 5xx
- import error rate
- login/magic link failures
- billing webhook failures
- latency i timeouty

Jeśli po deployu są dwa niezależne sygnały regresji, zatrzymaj dalsze zmiany do czasu wyjaśnienia.

## Known Operational Gaps

Na dziś ten runbook nadal zakłada domknięcie poniższych luk przed public prod:

- rollback drill
- restore drill
- oficjalny gate dla PIT-38 XSD
- legal opinion dla granicy produktu
- DPIA
- jawny release discipline dla finalnej linii releasowej

## Decision Log

| Date | Event | Decision | Owner | Evidence |
|---|---|---|---|---|
| YYYY-MM-DD | release / rollback / restore drill / incident | | | |
