/**
 * TaxPilot Load Test — Soak Test
 *
 * Sustained load over time to detect memory leaks and performance degradation.
 * Scenario: 10 VU constant for 10 minutes.
 *
 * Thresholds: p99 < 1000ms, error rate < 0.5%.
 *
 * Run (public endpoints only):
 *   k6 run tests/load/soak.js -e BASE_URL=http://localhost:8082
 *
 * Run with an authenticated session (also probes /dashboard and /import):
 *   k6 run tests/load/soak.js \
 *     -e BASE_URL=http://localhost:8082 \
 *     -e SESSION_COOKIE="PHPSESSID=<value>"
 *
 * Run via Docker Compose:
 *   docker compose --profile load-test run --rm k6 run /scripts/soak.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, htmlHeaders } from './config.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

// SESSION_COOKIE is optional — when set, authenticated endpoints are also probed.
const SESSION_COOKIE = __ENV.SESSION_COOKIE || '';

const errorRate = new Rate('soak_errors');
const publicDuration = new Trend('soak_public_duration');
const authDuration = new Trend('soak_auth_duration');

export const options = {
    scenarios: {
        soak: {
            executor: 'constant-vus',
            vus: 10,
            duration: '10m',
        },
    },
    thresholds: {
        http_req_duration: ['p(99)<1000'],
        soak_errors: ['rate<0.005'],
    },
    tags: {
        scenario: 'soak',
    },
};

export default function () {
    const headers = SESSION_COOKIE
        ? { ...htmlHeaders, Cookie: SESSION_COOKIE }
        : htmlHeaders;

    // Public endpoints — always probe
    const publicPages = ['/', '/login'];
    const publicPage = publicPages[Math.floor(Math.random() * publicPages.length)];

    const publicRes = http.get(`${BASE_URL}${publicPage}`, {
        headers,
        tags: { type: 'public' },
    });

    const publicOk = check(publicRes, {
        'public: status 200 or 302': (r) => r.status === 200 || r.status === 302,
    });

    publicDuration.add(publicRes.timings.duration);
    errorRate.add(!publicOk);

    // Authenticated endpoints — probe only when a session cookie is provided
    if (SESSION_COOKIE !== '') {
        const authPages = ['/dashboard', '/import'];
        const authPage = authPages[Math.floor(Math.random() * authPages.length)];

        const authRes = http.get(`${BASE_URL}${authPage}`, {
            headers,
            tags: { type: 'auth' },
        });

        // 200 = authenticated; 302 = session expired or redirect to /login — both are acceptable
        const authOk = check(authRes, {
            'auth: status 200 or 302': (r) => r.status === 200 || r.status === 302,
        });

        authDuration.add(authRes.timings.duration);
        errorRate.add(!authOk);
    }

    sleep(1);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/soak-summary.json': JSON.stringify(data, null, 2),
    };
}
