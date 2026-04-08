/**
 * TaxPilot Load Test - Scenario 2: Import Flow (Authenticated)
 *
 * Simulates logged-in users uploading broker files and viewing dashboard.
 * 50 VUs, 5 minutes, target p95 < 2s for import.
 */
import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { SharedArray } from 'k6/data';
import { BASE_URL, thresholds, stages, defaultHeaders, loginPayload } from './config.js';

// Custom metrics
const errorRate = new Rate('import_errors');
const loginDuration = new Trend('import_login_duration');
const uploadDuration = new Trend('import_upload_duration');
const dashboardDuration = new Trend('import_dashboard_duration');

export const options = {
    scenarios: {
        import_flow: {
            executor: 'constant-vus',
            vus: stages.import.vus,
            duration: stages.import.duration,
        },
    },
    thresholds: {
        ...thresholds.import,
        'import_upload_duration': ['p(95)<2000'],
    },
    tags: {
        scenario: 'import-flow',
    },
};

// Load CSV fixture as binary data
const csvFixture = open('/fixtures/revolut_stocks_sample.csv', 'b');
const CSRF_TOKEN = __ENV.CSRF_TOKEN || '';

export default function () {
    const vuId = __VU;

    // --- Step 1: Login (magic link bypass) ---
    const loginRes = http.post(
        `${BASE_URL}/login`,
        JSON.stringify(loginPayload(vuId)),
        {
            headers: defaultHeaders,
            tags: { step: 'login' },
        }
    );

    const loginOk = check(loginRes, {
        'login: status 200 or 302': (r) => r.status === 200 || r.status === 302,
    });

    loginDuration.add(loginRes.timings.duration);

    if (!loginOk) {
        errorRate.add(true);
        sleep(1);
        return; // skip rest of flow, retry on next iteration
    }

    sleep(0.5); // brief pause after login

    // --- Step 2: Upload broker file ---
    const uploadRes = http.post(
        `${BASE_URL}/import/upload`,
        {
            _token: CSRF_TOKEN,
            broker_id: 'revolut',
            force_reimport: '1',
            broker_file: http.file(csvFixture, 'revolut_stocks_sample.csv', 'text/csv'),
        },
        {
            tags: { step: 'upload' },
        }
    );

    const uploadOk = check(uploadRes, {
        'upload: status 200 or 302': (r) => r.status === 200 || r.status === 302,
        'upload: response time < 3s': (r) => r.timings.duration < 3000,
    });

    uploadDuration.add(uploadRes.timings.duration);
    errorRate.add(!uploadOk);

    sleep(1); // simulate user reviewing upload result

    // --- Step 3: View dashboard ---
    const dashRes = http.get(`${BASE_URL}/dashboard`, {
        tags: { step: 'dashboard' },
    });

    const dashOk = check(dashRes, {
        'dashboard: status 200': (r) => r.status === 200,
        'dashboard: response time < 2s': (r) => r.timings.duration < 2000,
    });

    dashboardDuration.add(dashRes.timings.duration);
    errorRate.add(!dashOk);

    // Think time between full iterations: 3-6 seconds
    sleep(Math.random() * 3 + 3);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/import-flow-summary.json': JSON.stringify(data, null, 2),
    };
}

import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';
