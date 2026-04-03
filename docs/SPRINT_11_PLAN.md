# Sprint 11 — Test Hardening + CI Pipeline

**Goal:** Close test strategy gaps, formalize CI, reach MSI 80%.

## Scope

### 1. GitHub Actions CI Pipeline
- Stage 1: lint (ECS) + stan (PHPStan) + unit tests (~2 min)
- Stage 2: integration + golden + property + contract + deptrac + infection (~5 min)
- Stage 3: security + smoke tests (~3 min)
- Trigger: push to main, PRs
- Quality gates: all stages green, MSI >= 80%, PHPStan 0, ECS 0, Deptrac 0

### 2. E2E/Acceptance Tests (Symfony Panther)
- Install symfony/panther
- 5+ tests covering critical user flows:
  1. Landing page loads, CTA visible
  2. Login via magic link flow
  3. CSV upload → import success
  4. Dashboard shows positions after import
  5. PIT-38 preview renders with data
- Separate PHPUnit suite: `e2e`

### 3. Chaos Tests
- 5+ tests simulating infrastructure failures:
  1. NBP API timeout → graceful degradation
  2. NBP API returns malformed JSON → error handling
  3. Database connection lost during import → rollback
  4. Redis unavailable → rate limiter fallback
  5. Stripe webhook with invalid signature → rejection
- Separate PHPUnit suite: `chaos`

### 4. MSI 76% → 80%
- Run Infection, identify top escaped mutants
- Target highest-value domain classes first (TaxCalc, FIFO)

### 5. Backlog cleanup
- Update BACKLOG.md statuses (P1-033, P1-034, P1-047 verified DONE/N/A)
- Add review findings from DAMA commit review

## Out of scope
- Production deploy (Sprint 12)
- XTB/mBank adapters (blocked on real CSV)
- E2E with real browser (Panther headless only)

## AC
- [ ] CI pipeline green on GitHub Actions
- [ ] 5+ E2E tests passing
- [ ] 5+ Chaos tests passing
- [ ] MSI >= 80%
- [ ] All review findings addressed
