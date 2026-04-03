/**
 * TaxPilot k6 Load Testing - Shared Configuration
 *
 * Base URL, thresholds, and reusable settings for all scenarios.
 * Override BASE_URL via environment variable: K6_BASE_URL
 */

export const BASE_URL = __ENV.K6_BASE_URL || 'http://app:8080';

// --- Thresholds ---

export const thresholds = {
    landing: {
        http_req_duration: ['p(95)<500'],   // p95 < 500ms
        http_req_failed: ['rate<0.01'],     // < 1% error rate
    },
    import: {
        http_req_duration: ['p(95)<2000'],  // p95 < 2s for import
        http_req_failed: ['rate<0.01'],
    },
    spike: {
        http_req_duration: ['p(95)<3000'],  // p95 < 3s under spike
        http_req_failed: ['rate==0'],       // 0% error rate
    },
};

// --- Stages ---

export const stages = {
    landing: {
        vus: 100,
        duration: '5m',
    },
    import: {
        vus: 50,
        duration: '5m',
    },
    spike: [
        { duration: '1m', target: 10 },    // warm up
        { duration: '3m', target: 50 },     // ramp to baseline
        { duration: '3m', target: 200 },    // ramp to peak (PIT season)
        { duration: '2m', target: 200 },    // hold peak
        { duration: '1m', target: 0 },      // cool down
    ],
};

// --- Auth helpers ---

/**
 * Simulates magic link login bypass for load testing.
 * In the real app, this would be a dedicated load-test endpoint
 * enabled only when APP_ENV=dev or APP_ENV=test.
 *
 * The endpoint should return a session cookie or JWT.
 */
export function loginPayload(vuIndex) {
    return {
        email: `loadtest+vu${vuIndex}@taxpilot.test`,
        _load_test_bypass: 'true',
    };
}

// --- Request defaults ---

export const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'text/html,application/json',
    'User-Agent': 'k6-load-test/1.0',
};

export const htmlHeaders = {
    'Accept': 'text/html,application/xhtml+xml',
    'User-Agent': 'k6-load-test/1.0',
};

// --- CSV fixture path (mounted into k6 container) ---

export const FIXTURE_CSV = '/fixtures/revolut_stocks_sample.csv';
