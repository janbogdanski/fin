# RUNBOOK — TaxPilot v1

## Metadata

| | |
|---|---|
| Status | DRAFT |
| Purpose | Operacyjny runbook dla deployu, smoke, rollbacku, restore i pierwszej reakcji incydentowej |
| Runtime | MyDevil + Cloudflare |
| Source of truth | `docs/DEPLOY.md`, `.github/workflows/deploy.yml`, `.github/workflows/release.yml` |

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

## Delivery Paths

### Path 1 — Main branch (continuous deploy)

Trafia na: **tę samą instancję MyDevil** co beta i produkcja.
W sezonie podatkowym (luty–kwiecień) rozważ ręczne zatwierdzanie deploy (aktualnie brak manual gate).

1. zmiana trafia na `main`
2. GitHub Actions uruchamia `CI` (`ci.yml`)
3. po zielonym `CI` uruchamia się `deploy.yml` (via `workflow_run: branches: [main]`)
4. deploy: `composer install --no-dev` → build Tailwind → `.env.local` → `rsync` → `migrate` → `warmup` → smoke test

### Path 2 — Version tag (RC / production release)

Deploy uruchamia się dopiero gdy **Stage 2 AND Stage 3 oba przejdą** (`needs: [stage-2-integration, stage-3-security-smoke]`).

1. `git tag v0.1.0-rc.1 && git push origin v0.1.0-rc.1`
2. GitHub Actions uruchamia `release.yml` (triggered directly on `push: tags: ['v[0-9]*']`)
3. Stage 1: lint + PHPStan + unit (blokuje 2 i 3)
4. Stage 2 (parallel z 3): integration + golden + property + contract + mutation
5. Stage 3 (parallel z 2): security + E2E
6. **Deploy TYLKO gdy Stage 2 AND Stage 3 zielone** — gwarancja quality gate
7. rsync → migrate → warmup → smoke test + `echo "Deployed tag: v0.1.0-rc.1"`

### Target Hardening (pre-public-prod)

Przed public prod wymagane jeszcze:

- staging/prod-like deploy rehearsal (BETA-BLK-007)
- rollback drill (PROD-BLK-001)
- restore drill (PROD-BLK-002)
- alert test (BETA-BLK-008)

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

**Beta RC (release candidate):**
```bash
git checkout main
git pull --ff-only
git tag -a v0.1.0-rc.1 -m "RC1: scope frozen, XSD green, pipeline tested"
git push origin v0.1.0-rc.1
# observe: github.com/{owner}/fin/actions → Release workflow
```

**Production release:**
```bash
git tag -a v0.1.0 -m "v0.1.0: closed beta green, legal cleared"
git push origin v0.1.0
```

**Hotfix na main (CI + auto-deploy):**
- merge lub push do `main`
- obserwuj `CI` → `Deploy to MyDevil`

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

## Contact Points

> **Uzupełnij przed zamknięciem BETA-BLK-009.** Wymaga decyzji Product Ownera.

| Rola | Osoba | Kanał | SLA |
|---|---|---|---|
| Incident Owner (P0) | TBD | TBD | 15 min |
| Deploy Owner | TBD | TBD | — |
| Support Owner (beta feedback) | TBD | TBD | 24h |
| Legal / Compliance escalation | TBD | TBD | 48h |

## Feedback Loop (Beta)

Ścieżka zbierania i triage'u feedbacku z zamkniętej bety:

1. **Kanał feedbacku:** TBD (email / formularz) — ustalić przed otwarciem bety
2. **Owner triage:** TBD — sprawdza kanał co TBD (np. codziennie w godzinach pracy)
3. **Klasyfikacja:**
   - Bug obliczeniowy / XML error → P0, natychmiastowy stop + hotfix
   - UX / import error dla wspieranego brokera → P1, fix w ciągu 24h
   - Sugestia / broker nieobsługiwany → backlog
4. **Rytm review:** TBD (np. co tydzień spotkanie feedback review)
5. **Artefakt wynikowy:** wpis w `docs/PROD_BLOCKERS.md` lub BACKLOG.md

> **Do zamknięcia BETA-BLK-010:** uzupełnić TBD powyżej i potwierdzić z PO.

## Known Operational Gaps

Luki wymagające domknięcia przed public prod:

| Gap | Status | Blocker |
|---|---|---|
| Rollback drill | TODO | PROD-BLK-001 |
| Restore drill | TODO | PROD-BLK-002 |
| PIT-38 XSD gate | **DONE** (2026-04-14) | BETA-BLK-005 CLOSED |
| Release discipline + tag pipeline | **DONE** (2026-04-14) | BETA-BLK-002 READY FOR VERIFY |
| Legal opinion (narzędzie vs PIT) | EXTERNAL | BETA-BLK-003 |
| DPIA | EXTERNAL | BETA-BLK-004 |
| Monitoring aktywacja | TODO | BETA-BLK-008 |
| Contact points + feedback loop | TODO | BETA-BLK-009 + BETA-BLK-010 |

## Decision Log

| Date | Event | Decision | Owner | Evidence |
|---|---|---|---|---|
| YYYY-MM-DD | release / rollback / restore drill / incident | | | |
