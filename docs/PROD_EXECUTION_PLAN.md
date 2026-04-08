# PROD EXECUTION PLAN — TaxPilot

## Metadata

| | |
|---|---|
| Status | DRAFT |
| Purpose | Plan dojścia do production dla v1 na MyDevil + Cloudflare |
| Basis | `docs/adr/*`, `.github/workflows/*.yml`, `docs/DEPLOY.md`, `docs/TEST_METRICS.md`, `docs/REVIEW_CONSOLIDATED.md` |
| Operating style | TDD, DDD, review-first, short feedback loop, small batch changes |

## Outcome

Production oznacza tutaj:

| Criterion | Target |
|---|---|
| Release path | Tagged build, green CI, automated deploy |
| Runtime | MyDevil v1, Cloudflare in front, PHP 8.4, Postgres 17, Redis |
| Quality gate | Unit, integration, golden, property, contract, security, E2E, static analysis |
| Safety gate | Monitoring, backups, rollback, runbook, secrets discipline |
| Product gate | PIT-38 flow validated, legal/compliance blockers resolved |

## Execution Assets

| Asset | Purpose |
|---|---|
| `docs/PROD_EXECUTION_PLAN.md` | Plan wykonawczy i gate'y releasowe |
| `docs/PROD_BLOCKERS.md` | Aktualny rejestr blockerów beta i public prod |
| `docs/RELEASE_READINESS_CHECKLIST.md` | Checklista gotowości do beta, cutover i public launch |
| `docs/RUNBOOK.md` | Procedura release, smoke, rollback, restore i incident triage |
| `docs/agents/prod-readiness-orchestrator-agent-prompt.md` | Orkiestracja agent team dla readiness, review i feedback loop |
| `docs/AUDIT_PIPELINE.md` | Katalog audytow i cadence przed releasem |
| `.github/workflows/ci.yml` | Dowod wykonania quality gate'ow |
| `.github/workflows/deploy.yml` | Dowod powtarzalnego deployu |

## Operating Model

### Team topology

| Team | Responsibility | Output |
|---|---|---|
| Core Delivery | DDD/TDD implementation in bounded contexts | Small, test-backed increments |
| Platform/DevOps | CI/CD, deploy, hosting, backups, monitoring | Reproducible release path |
| QA/Testing | Test design, regressions, quality gates | Red-green-refactor evidence |
| Security/Compliance | Secrets, auth, PII, GDPR, legal gates | Go/No-Go risk decisions |
| Tax/Legal Review | PIT-38 correctness and advisory boundary | Domain sign-off |

### Review loop

1. Plan one small vertical slice.
2. Write or update tests first.
3. Implement only the minimum code to pass.
4. Run static analysis and targeted suites.
5. Review findings with the relevant reviewer role.
6. Merge only after gates are green.
7. Record residual risks and next slice.

## Workstreams

### 1. Product correctness

Scope:
- PIT-38 generation
- broker import flows
- calculation correctness
- golden dataset coverage

Deliverables:
- validated PIT-38 XML flow
- extended golden scenarios
- domain regressions fixed by tests

### 2. Platform readiness

Scope:
- MyDevil deploy flow
- Cloudflare edge protection
- secrets handling
- backup and restore
- smoke checks and runbook

Deliverables:
- green deploy pipeline
- documented production setup
- tested rollback path

### 3. Quality gates

Scope:
- TDD enforcement
- static analysis
- contract tests
- mutation tests
- E2E and security suites

Deliverables:
- release gate checklist
- known test gaps tracked
- non-green suites either fixed or explicitly deferred

### 4. Compliance and risk

Scope:
- DPIA
- legal boundary for PIT-38 XML
- retention policy
- secrets policy
- audit trail expectations

Deliverables:
- explicit go/no-go on compliance blockers
- documented assumptions and limitations

## Phases

### Phase 0: Freeze scope

Goal: stop scope creep and define the release line.

Tasks:
- lock v1 scope to supported brokers and supported user journeys
- create and keep current `docs/PROD_BLOCKERS.md`
- separate production blockers from backlog
- decide what is deferred to v1.1
- tag the release candidate branch

Exit criteria:
- v1 scope approved
- blockers named and assigned
- no uncontrolled feature work entering release branch

### Phase 1: Close blockers

Goal: remove hard blockers before any beta traffic.

Tasks:
- resolve legal/compliance blockers
- close known test gaps on the critical path
- verify PIT-38 XML schema validation path
- confirm secrets and retention decisions

Exit criteria:
- no open P0/P1 in security, legal, or correctness
- critical path tests green
- compliance gates explicitly signed off

### Phase 2: Harden delivery

Goal: make the release repeatable and observable.

Tasks:
- verify CI on release branch
- verify deploy to staging-like target
- add monitoring and alerts
- test backup and restore
- document rollback steps

Exit criteria:
- CI green on tagged release candidate
- deploy works end to end
- rollback and restore tested once
- runbook exists and is usable

### Phase 3: Closed beta

Goal: validate with limited real users.

Tasks:
- onboard a small user set
- monitor import errors, payment flow, and XML generation
- collect feedback daily
- fix only production-significant issues

Exit criteria:
- no unresolved P0 production incidents
- feedback loop is stable
- support load is understood

### Phase 4: Public launch

Goal: open production to the full intended audience.

Tasks:
- lift beta restrictions
- keep monitoring and alerting active
- keep release cadence small
- continue post-launch triage

Exit criteria:
- release process is stable
- operational ownership is clear
- remaining work is backlog, not launch blocking

## Gate Criteria

### Release gate

| Gate | Required state |
|---|---|
| Tests | Unit, integration, golden, property, contract, security, E2E green |
| Static analysis | ECS, PHPStan, Deptrac green |
| Dependency audit | `composer audit` green |
| Compliance | DPIA and legal boundary reviewed |
| Deploy | Staging deploy and smoke test passed |
| Rollback | Re-deploy previous tag tested |
| Observability | Health endpoint, logs, alerts, error tracking in place |

### Quality gate policy

- Fail fast on test failures.
- Do not merge around broken suites.
- Do not accept false-green tests.
- Any skipped check must have an owner and a due date.

## Owners And Roles

| Role | Decision area | Typical output |
|---|---|---|
| Product Owner | Scope and launch priority | what ships now vs later |
| Tech Lead | Release readiness | go/no-go synthesis |
| Senior Dev | Architecture and implementation fit | design choices, refactors |
| QA Lead | Coverage and scenario completeness | test matrix, regressions |
| DevOps | deployment and runtime | pipeline, rollback, hosting |
| Security Auditor | auth, secrets, PII, attack surface | blocker or conditional go |
| Tax Advisor | calculation and legal correctness | domain sign-off |
| Legal Reviewer | advisory boundary and compliance | launch constraint assessment |

## Risk Register

| Risk | Impact | Mitigation | Owner |
|---|---|---|---|
| PIT-38 XML schema drift | User-facing export rejection | schema validation gate, sample fixture, regression test | QA Lead |
| Legal boundary violation | Launch blocked or re-scope required | opinion + explicit product scope | Legal Reviewer |
| Secrets leak | Security incident | env-only secrets, no repo secrets, rotation plan | DevOps |
| Redis / queue failure | broken async or rate limiting | health checks, prod fallback, smoke coverage | DevOps |
| Backup not restorable | data loss recovery failure | test restore, not just backup | DevOps |
| Contract suite fragility | false confidence or blocked CI | seed data, isolated fixtures, explicit ownership | QA Lead |
| Import performance regression | poor user experience at peak | load tests, targeted profiling, defer heavy optimization | Senior Dev |
| Monitoring gap | slow incident detection | logs, alerts, Sentry, uptime probe | DevOps |

## First 2 Weeks

### Week 1

1. Freeze release scope and assign owners.
2. Create or update the blocker register and assign next actions.
3. Verify release branch CI and fix any red suites.
4. Add or finalize PIT-38 XML schema validation gate.
5. Confirm MyDevil staging setup, secrets flow, and smoke test.
6. Write the first version of the runbook.

### Week 2

1. Run a full staging deploy from tag.
2. Execute restore drill and rollback drill.
3. Run a closed beta rehearsal with a small internal group.
4. Review monitoring and alerts with the team.
5. Triage feedback and classify fixes as launch-blocking or backlog.
6. Make the go/no-go decision for beta or production launch.

## Feedback Loop

| Cadence | Participants | Purpose |
|---|---|---|
| Daily | Delivery + QA + DevOps | unblock work and catch regressions fast |
| Twice weekly | Tech Lead + Security + Tax/Legal | resolve blockers and scope conflicts |
| Per merge | Relevant reviewer | keep changes small and test-backed |
| End of week | Whole team | adjust plan from actual findings |

Rules:
- Prefer one vertical slice over many partial changes.
- Every finding gets an owner and a next action.
- If a gate fails twice, stop and re-plan instead of pushing through.
- Run the Prod Readiness Orchestrator before beta, before release cutover, and after each larger blocker-clearing batch.
- Keep the release plan up to date with actual evidence, not optimism.

## Go / No-Go Summary

Production go-live is allowed only when:

- release branch is green
- no open P0/P1 blockers remain
- deploy and rollback are proven
- monitoring and support are ready
- legal/compliance has no unresolved launch blocker

If any of those are false, the correct decision is `NO-GO` for public launch and `GO` only for the next controlled step.
