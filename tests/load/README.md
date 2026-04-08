# Load Tests (k6)

These scripts are NOT part of the CI pipeline. They are manual / performance engineering tools.

## Prerequisites

- [k6](https://k6.io/docs/getting-started/installation/) installed locally, OR
- Docker Compose stack running (`make dev`) — the `k6` service is defined with the `load-test` profile.

The app is exposed on `http://localhost:8082` in the default Docker Compose setup.
Inside the Docker network the app is reachable at `http://app:8080`.

---

## Scripts

| Script | Type | VUs | Duration | Thresholds |
|---|---|---|---|---|
| `landing.js` | Baseline | 100 constant | 5 min | p95 < 500ms, errors < 1% |
| `import-flow.js` | Baseline | 50 constant | 5 min | p95 < 2s, errors < 1% |
| `pit-season.js` | Spike (PIT season) | 0 → 200 | ~10 min | p95 < 3s, errors = 0% |
| `spike.js` | Spike (sharp) | 0 → 50 → 0 | ~100s | p95 < 500ms, errors < 1% |
| `soak.js` | Soak | 10 constant | 10 min | p99 < 1000ms, errors < 0.5% |
| `concurrent-import.js` | Stress (upload) | 5 constant | 2 min | p95 < 2s, import errors < 5% |

---

## Running locally (k6 binary installed)

```bash
# Spike test
k6 run tests/load/spike.js -e K6_BASE_URL=http://localhost:8082

# Import flow (requires a valid import-form CSRF token)
k6 run tests/load/import-flow.js \
  -e K6_BASE_URL=http://localhost:8082 \
  -e CSRF_TOKEN="<token-from-import-form>"

# Soak test (public endpoints only)
k6 run tests/load/soak.js -e K6_BASE_URL=http://localhost:8082

# Soak test with an authenticated session
k6 run tests/load/soak.js \
  -e K6_BASE_URL=http://localhost:8082 \
  -e SESSION_COOKIE="PHPSESSID=<your-session-id>"

# Concurrent broker-file import (requires session + CSRF token)
k6 run tests/load/concurrent-import.js \
  -e K6_BASE_URL=http://localhost:8082 \
  -e SESSION_COOKIE="PHPSESSID=<your-session-id>" \
  -e CSRF_TOKEN="<token-from-import-form>"
```

---

## Running via Docker Compose

The `k6` service in `docker-compose.yml` mounts:
- `./tests/load` → `/scripts` inside the container
- `./tests/Fixtures` → `/fixtures` (read-only) inside the container
- `./var/load-results` → `/results` inside the container

The Makefile has shortcuts for the three baseline scenarios:

```bash
make load-test-landing   # runs landing.js
make load-test-import    # runs import-flow.js
make load-test-spike     # runs pit-season.js
```

For the new P2-070 scripts, run them directly:

```bash
# Spike
docker compose --profile load-test run --rm k6 run /scripts/spike.js \
  -e K6_BASE_URL=http://app:8080

# Soak
docker compose --profile load-test run --rm k6 run /scripts/soak.js \
  -e K6_BASE_URL=http://app:8080

# Concurrent import
docker compose --profile load-test run --rm k6 run /scripts/concurrent-import.js \
  -e K6_BASE_URL=http://app:8080 \
  -e SESSION_COOKIE="PHPSESSID=<value>" \
  -e CSRF_TOKEN="<value>"
```

Note: `config.js` defaults BASE_URL to `http://app:8080` (Docker internal network).
When running outside Docker, always pass `-e K6_BASE_URL=http://localhost:8082`.

---

## Obtaining a session cookie and CSRF token

The application uses magic-link authentication (no password). Steps:

1. Start the stack: `make dev`
2. Open `http://localhost:8082/login` in a browser.
3. Enter a seeded user email and request a magic link.
4. Open Mailpit at `http://localhost:8026` and click the magic link.
5. Open DevTools → Application → Cookies → copy the `PHPSESSID` value.
6. Navigate to `http://localhost:8082/import`, view page source, find the hidden `_token` input.

For automated provisioning, add a test-only backdoor endpoint (guarded by `APP_ENV=test`)
that issues a pre-authenticated session without going through the mail flow.

---

## Fixtures

- `tests/load/fixtures/minimal.csv` — 3-row Revolut Stocks CSV for quick smoke uploads.
- `tests/Fixtures/revolut_stocks_sample.csv` — full fixture used by `concurrent-import.js` and `import-flow.js` inside Docker.

---

## Output

Results land in `var/load-results/` as JSON when running via Docker Compose.

To generate a summary report:

```bash
docker compose --profile load-test run --rm k6 run /scripts/spike.js \
  -e K6_BASE_URL=http://app:8080 \
  --out json=/results/spike.json
```

Visualise with [k6 reporter](https://github.com/benc-uk/k6-reporter) or the Grafana k6 dashboard.
