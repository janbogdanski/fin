/**
 * TaxPilot Load Test - Scenario 3: PIT Season Spike
 *
 * Simulates realistic PIT filing season traffic pattern.
 * Ramp 10 -> 200 VUs over 10 minutes.
 * Traffic mix: 60% landing, 20% import, 15% dashboard, 5% XML export.
 * Target: p95 < 3s, 0% error rate.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { BASE_URL, thresholds, stages, defaultHeaders, htmlHeaders, loginPayload } from './config.js';

// Custom metrics
const errorRate = new Rate('spike_errors');
const landingDuration = new Trend('spike_landing_duration');
const importDuration = new Trend('spike_import_duration');
const dashboardDuration = new Trend('spike_dashboard_duration');
const exportDuration = new Trend('spike_export_duration');
const flowCounter = new Counter('spike_flow_count');

export const options = {
    scenarios: {
        pit_season_spike: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: stages.spike,
            gracefulRampDown: '30s',
        },
    },
    thresholds: thresholds.spike,
    tags: {
        scenario: 'pit-season',
    },
};

// Load CSV fixture for import flow
const csvFixture = open('/fixtures/revolut_stocks_sample.csv', 'b');

// --- Flow definitions ---

function flowLanding() {
    flowCounter.add(1, { flow: 'landing' });

    const pages = ['/', '/cennik', '/blog'];
    const page = pages[Math.floor(Math.random() * pages.length)];

    const res = http.get(`${BASE_URL}${page}`, {
        headers: htmlHeaders,
        tags: { flow: 'landing' },
    });

    const ok = check(res, {
        'landing: status 200': (r) => r.status === 200,
    });

    landingDuration.add(res.timings.duration);
    errorRate.add(!ok);

    sleep(Math.random() * 2 + 1);
}

function flowImport() {
    flowCounter.add(1, { flow: 'import' });
    const vuId = __VU;

    // Login
    const loginRes = http.post(
        `${BASE_URL}/login`,
        JSON.stringify(loginPayload(vuId)),
        {
            headers: defaultHeaders,
            tags: { flow: 'import', step: 'login' },
        }
    );

    if (loginRes.status !== 200 && loginRes.status !== 302) {
        errorRate.add(true);
        return;
    }

    sleep(0.5);

    // Upload
    const uploadRes = http.post(
        `${BASE_URL}/import/upload`,
        {
            file: http.file(csvFixture, 'revolut_stocks_sample.csv', 'text/csv'),
            broker: 'revolut',
        },
        {
            tags: { flow: 'import', step: 'upload' },
        }
    );

    const ok = check(uploadRes, {
        'import: status 200 or 302': (r) => r.status === 200 || r.status === 302,
    });

    importDuration.add(uploadRes.timings.duration);
    errorRate.add(!ok);

    sleep(Math.random() * 2 + 1);
}

function flowDashboard() {
    flowCounter.add(1, { flow: 'dashboard' });
    const vuId = __VU;

    // Login first
    const loginRes = http.post(
        `${BASE_URL}/login`,
        JSON.stringify(loginPayload(vuId)),
        {
            headers: defaultHeaders,
            tags: { flow: 'dashboard', step: 'login' },
        }
    );

    if (loginRes.status !== 200 && loginRes.status !== 302) {
        errorRate.add(true);
        return;
    }

    sleep(0.3);

    const res = http.get(`${BASE_URL}/dashboard`, {
        tags: { flow: 'dashboard' },
    });

    const ok = check(res, {
        'dashboard: status 200': (r) => r.status === 200,
    });

    dashboardDuration.add(res.timings.duration);
    errorRate.add(!ok);

    sleep(Math.random() * 3 + 2);
}

function flowExport() {
    flowCounter.add(1, { flow: 'export' });
    const vuId = __VU;

    // Login first
    const loginRes = http.post(
        `${BASE_URL}/login`,
        JSON.stringify(loginPayload(vuId)),
        {
            headers: defaultHeaders,
            tags: { flow: 'export', step: 'login' },
        }
    );

    if (loginRes.status !== 200 && loginRes.status !== 302) {
        errorRate.add(true);
        return;
    }

    sleep(0.3);

    // Request PIT-38 XML export
    const res = http.get(`${BASE_URL}/export/pit-38/xml`, {
        tags: { flow: 'export' },
    });

    const ok = check(res, {
        'export: status 200': (r) => r.status === 200,
        'export: content-type xml': (r) =>
            r.headers['Content-Type'] && r.headers['Content-Type'].includes('xml'),
    });

    exportDuration.add(res.timings.duration);
    errorRate.add(!ok);

    sleep(Math.random() * 2 + 1);
}

// --- Traffic distribution ---

const TRAFFIC_MIX = [
    { flow: flowLanding, weight: 60 },
    { flow: flowImport, weight: 20 },
    { flow: flowDashboard, weight: 15 },
    { flow: flowExport, weight: 5 },
];

function pickFlow() {
    const roll = Math.random() * 100;
    let cumulative = 0;
    for (const entry of TRAFFIC_MIX) {
        cumulative += entry.weight;
        if (roll < cumulative) {
            return entry.flow;
        }
    }
    return TRAFFIC_MIX[0].flow;
}

export default function () {
    const flow = pickFlow();
    flow();
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/pit-season-summary.json': JSON.stringify(data, null, 2),
    };
}

import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';
