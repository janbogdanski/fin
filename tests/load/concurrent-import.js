/**
 * TaxPilot Load Test — Concurrent Broker File Import
 *
 * 5 VU each uploading a small Revolut broker file repeatedly to stress the import pipeline.
 *
 * Prerequisites:
 *   - App running and database migrated.
 *   - Authenticated session cookie: SESSION_COOKIE env var.
 *     Example: SESSION_COOKIE=PHPSESSID=abc123xyz
 *   - Valid CSRF token for the import form: CSRF_TOKEN env var.
 *     Obtain by loading /import in a browser and inspecting the hidden _token field.
 *
 * Note on CSRF:
 *   Symfony validates the _token field on POST /import/upload. The token is session-scoped.
 *   For a realistic multi-user test, provision one session+token pair per VU. A test-only
 *   bypass endpoint (guarded by APP_ENV=test) is recommended for CI/staging automation.
 *
 * Run locally:
 *   k6 run tests/load/concurrent-import.js \
 *     -e K6_BASE_URL=http://localhost:8082 \
 *     -e SESSION_COOKIE="PHPSESSID=<value>" \
 *     -e CSRF_TOKEN="<token>"
 *
 * Run via Docker Compose (fixture auto-mounted at /fixtures):
 *   docker compose --profile load-test run --rm k6 run /scripts/concurrent-import.js \
 *     -e K6_BASE_URL=http://app:8080 \
 *     -e SESSION_COOKIE="PHPSESSID=<value>" \
 *     -e CSRF_TOKEN="<token>"
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { SharedArray } from 'k6/data';
import { BASE_URL } from './config.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

const SESSION_COOKIE = __ENV.SESSION_COOKIE || '';
const CSRF_TOKEN = __ENV.CSRF_TOKEN || '';

const errorRate = new Rate('concurrent_import_errors');
const importDuration = new Trend('concurrent_import_duration');

// Minimal Revolut fixture — loaded once, shared across all VUs.
// Docker Compose mounts tests/Fixtures at /fixtures inside the container.
// When running locally with k6 installed, the path is relative to the script.
const csvFixture = open('/fixtures/revolut_stocks_sample.csv', 'b');

export const options = {
    scenarios: {
        concurrent_import: {
            executor: 'constant-vus',
            vus: 5,
            duration: '2m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<2000'],
        concurrent_import_errors: ['rate<0.05'],
    },
    tags: {
        scenario: 'concurrent-import',
    },
};

export default function () {
    if (SESSION_COOKIE === '') {
        console.warn(
            `VU ${__VU}: SESSION_COOKIE is not set — ` +
            'POST /import/upload will be rejected (redirect to /login). ' +
            'Pass -e SESSION_COOKIE=PHPSESSID=<value>',
        );
    }

    const headers = {
        Cookie: SESSION_COOKIE,
    };

    const body = {
        _token: CSRF_TOKEN,
        broker_id: 'revolut',
        force_reimport: '1',
        broker_file: http.file(csvFixture, 'revolut_stocks_sample.csv', 'text/csv'),
    };

    const res = http.post(`${BASE_URL}/import/upload`, body, {
        headers,
        tags: { step: 'upload' },
    });

    importDuration.add(res.timings.duration);

    // 200 = import rendered results page (success or parse errors shown inline)
    // 302 = CSRF invalid / rate-limited / already imported without force flag
    // 5xx = server error — always a failure
    const ok = check(res, {
        'import: response is not 5xx': (r) => r.status < 500,
        'import: response time < 2s': (r) => r.timings.duration < 2000,
    });

    errorRate.add(!ok);

    sleep(2);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/concurrent-import-summary.json': JSON.stringify(data, null, 2),
    };
}
