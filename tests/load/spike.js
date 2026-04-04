/**
 * TaxPilot Load Test — Spike Test
 *
 * Simulates a sudden traffic spike to public pages (/ and /login).
 * Scenario: ramp from 0 to 50 VU in 30s, hold for 60s, drop to 0 in 10s.
 *
 * Thresholds: p95 < 500ms, error rate < 1%.
 *
 * Run locally:
 *   k6 run tests/load/spike.js -e BASE_URL=http://localhost:8082
 *
 * Run via Docker Compose:
 *   docker compose --profile load-test run --rm k6 run /scripts/spike.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, htmlHeaders } from './config.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

const errorRate = new Rate('spike_simple_errors');
const landingDuration = new Trend('spike_landing_duration');
const loginDuration = new Trend('spike_login_duration');

export const options = {
    scenarios: {
        spike: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 50 },  // ramp up
                { duration: '60s', target: 50 },  // hold
                { duration: '10s', target: 0 },   // ramp down
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500'],
        spike_simple_errors: ['rate<0.01'],
    },
    tags: {
        scenario: 'spike',
    },
};

export default function () {
    const roll = Math.random();

    if (roll < 0.6) {
        // 60% landing page
        const res = http.get(`${BASE_URL}/`, {
            headers: htmlHeaders,
            tags: { page: 'home' },
        });

        const ok = check(res, {
            'home: status 200': (r) => r.status === 200,
        });

        landingDuration.add(res.timings.duration);
        errorRate.add(!ok);
    } else {
        // 40% login page
        const res = http.get(`${BASE_URL}/login`, {
            headers: htmlHeaders,
            tags: { page: 'login' },
        });

        const ok = check(res, {
            'login: status 200': (r) => r.status === 200,
        });

        loginDuration.add(res.timings.duration);
        errorRate.add(!ok);
    }

    sleep(1);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/spike-summary.json': JSON.stringify(data, null, 2),
    };
}
