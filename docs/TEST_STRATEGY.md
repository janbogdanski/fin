# TaxPilot — Test Strategy

Goal: Every commit on main is releasable (trunk-based development).

## Test Pyramid (12 levels)

| Level | Current | Target Beta | When | Tools |
|---|---|---|---|---|
| Unit | 573 | 700+ | Every push, stage 1 | PHPUnit |
| Integration | 65 | 80+ | Every push, stage 2 | WebTestCase + DAMA |
| Golden Dataset | 11 | 20 | Every push, stage 2 | PHPUnit, tax advisor sign-off |
| Property | 4 | 10+ | Every push, stage 2 | PHPUnit random |
| Contract (Pact) | 3 | 5+ | Every push, stage 2 | pact-php v10 |
| Mutation | MSI 76% | MSI 80% | Every push, stage 2 | Infection |
| Security | 21 | 25+ | Every push, stage 3 | WebTestCase |
| Smoke | 20+ | 20+ | Every push, stage 3 | WebTestCase |
| Performance | 3 k6 | 3+ | Nightly | k6 |
| Canary | 3 | 5+ | Nightly | PHPUnit, real API |
| E2E/Acceptance | 0 | 5+ | Merge to main | Panther |
| Chaos | 0 | 5+ | Nightly | PHPUnit mocks |

## CI Pipeline

```
PR push (stage 1-3, ~8 min):
  1. lint + stan + unit tests (<2 min)
  2. integration + golden + property + contract + deptrac + infection (<5 min)
  3. security + smoke (<3 min)

Merge to main (stage 1-4):
  4. E2E acceptance (Panther, <5 min)
  5. pact-publish

Nightly cron:
  canary + load test + chaos + composer audit
```

## Quality Gates (before merge)

- Pipeline stages 1-3 GREEN
- MSI >= 80% (Infection)
- PHPStan level max, 0 errors
- ECS 0, Deptrac 0
- Code review: no P0/P1
- Security review: no P0 (when triggered)

## Coverage Targets

| Metric | Current | Beta | Prod |
|---|---|---|---|
| Unit line coverage | ? | 85% | 90% |
| MSI | 76% | 80% | 85% |
| Integration tests | 65 | 80+ | 100+ |
| Golden datasets | 11 | 20 | 30+ |
| Security tests | 21 | 25+ | 35+ |
| E2E | 0 | 5+ | 10+ |

## Key policies

- Every bug fix starts with failing test (TDD)
- P0 tax calc bug → new golden dataset
- ClockInterface for all time-dependent code (PSR-20)
- DAMA DoctrineTestBundle for DB isolation
- ObjectMother pattern for test factories
- Flaky test → quarantine within 24h, fix within 1 sprint

## make ci (target)

```makefile
ci: ci-stage1 ci-stage2 ci-stage3
ci-stage1: lint stan test-unit
ci-stage2: test-integration test-golden test-property test-contract deptrac infection
ci-stage3: test-security test-smoke
ci-full: ci test-e2e
ci-nightly: test-canary load-test-spike test-chaos
```

Full strategy details: see agent output in session history.
