# Release Strategy — TaxPilot

## Metadata

| | |
|---|---|
| Status | ACTIVE |
| Purpose | Release branch/tag policy and deploy path for beta and production |
| Closes | BETA-BLK-002 (when RC tag successfully deployed) |
| Runtime | MyDevil (PHP 8.4, Postgres 17, Redis 7) via rsync + Cloudflare |

---

## Branch Model

TaxPilot uses **trunk-based development**. There are no long-lived `release/` branches.

| Branch | Purpose | Deploy |
|---|---|---|
| `main` | Always deployable. CI must be green before merge. | Auto-deploy on every push |
| `feature/*` | Short-lived. Must be merged via PR with CI green. | Never deployed |
| Tags `v*` | Release candidates and production releases. | Deployed on CI-green |

---

## Tag Scheme

```
v{major}.{minor}.{patch}[-rc.{n}]
```

| Tag | Meaning | Example |
|---|---|---|
| `v0.1.0-rc.1` | First release candidate for 0.1.0 | Closed beta rehearsal |
| `v0.1.0-rc.2` | Second RC (if issues found) | After RC1 fixes |
| `v0.1.0` | Production release | Public launch |

**Rules:**
- Tags are created from `main` HEAD only.
- Tags are immutable. If a bug is found in an RC, push a new RC tag.
- Never re-tag (no force-push of tags).
- Tag message should contain what changed since previous tag.

---

## CI and Deploy Pipeline

### Triggers

| Event | Workflow | Deploy Runs |
|---|---|---|
| Push to `main` | `ci.yml` → `deploy.yml` (via workflow_run) | Yes (auto) |
| Pull request to `main` | `ci.yml` | No |
| Push tag `v*` | `release.yml` (CI + deploy combined) | Yes, if all CI stages green |

### Architecture

There are **two separate pipelines**:

- **`ci.yml` + `deploy.yml`**: for `main` branch. CI runs, then `deploy.yml` triggers via `workflow_run`.
- **`release.yml`**: for version tags. Single workflow that runs full CI suite, then deploys. No `workflow_run` dependency — avoids `head_branch` unreliability for tag push events.

### CI → Deploy flow for tags

```
git tag v0.1.0-rc.1
git push origin v0.1.0-rc.1
          ↓
    release.yml triggers
          ↓
    Stage 1: lint + stan + unit
          ↓
    Stage 2: integration + golden + property + contract + mutation  (parallel)
    Stage 3: security + E2E                                          (parallel)
          ↓ (both must pass)
    deploy: rsync → migrate → warmup → smoke test
          ↓
    echo "Deployed tag: v0.1.0-rc.1"
```

### CI suite on tag (BETA-BLK-006 gate)

All stages must be green:
- Stage 1: ECS lint, PHPStan level 9, PHPUnit unit
- Stage 2: Integration, golden datasets, property, contract, security, E2E, audit suites
- Stage 3: ADR drift check, CI-ONLY secrets guard

---

## How to Cut a Release

### Step 1 — Verify main is green

```bash
gh run list --branch main --limit 5
```

All recent runs must show ✅.

### Step 2 — Create and push tag

```bash
git checkout main
git pull --ff-only
git tag -a v0.1.0-rc.1 -m "RC1: PIT-38 validated, XSD green, scope frozen"
git push origin v0.1.0-rc.1
```

### Step 3 — Watch CI + deploy

```bash
gh run list --limit 3
```

CI workflow runs on the tag. On success, deploy workflow triggers automatically.

### Step 4 — Verify smoke test

Check the smoke test step in deploy run. It must return HTTP 200 or 302.

```bash
gh run view --log | grep "Smoke test"
```

### Step 5 — Record evidence

Update `docs/PROD_BLOCKERS.md` with:
- CI run link
- Deploy run link
- Smoke test result
- Change BETA-BLK-002 status to CLOSED

---

## Rollback

If deploy breaks production:

1. Identify last known good tag: `git tag --sort=-creatordate | head -5`
2. Push that tag again with `-rc.{n}` incremented OR re-trigger deploy workflow manually:

```bash
gh workflow run deploy.yml --ref main
```

3. Document incident in `docs/RUNBOOK.md`.

Full rollback procedure: see `docs/RUNBOOK.md#rollback`.

---

## Deploy Targets

| Environment | Trigger | Target |
|---|---|---|
| Production (MyDevil) | Push to `main` OR CI-green on `v*` tag | `$MYDEVIL_DEPLOY_PATH` via rsync |

There is currently **one environment**. RC tags and production tags deploy to the same server. This is intentional for v1 (single-tenant MyDevil hosting). A staging environment is deferred to v2.

---

## Decision Record

| Date | Decision | Who |
|---|---|---|
| 2026-04-14 | Trunk-based + tag-only releases, no release branches, single MyDevil env | Tech Lead |
