/**
 * TaxPilot Load Test - Scenario 1: Landing Page (Public)
 *
 * Simulates anonymous visitors browsing public pages.
 * 100 VUs, 5 minutes, target p95 < 500ms.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, thresholds, stages, htmlHeaders } from './config.js';

// Custom metrics
const errorRate = new Rate('landing_errors');
const homeDuration = new Trend('landing_home_duration');
const pricingDuration = new Trend('landing_pricing_duration');
const blogDuration = new Trend('landing_blog_duration');

export const options = {
    scenarios: {
        landing_pages: {
            executor: 'constant-vus',
            vus: stages.landing.vus,
            duration: stages.landing.duration,
        },
    },
    thresholds: thresholds.landing,
    tags: {
        scenario: 'landing',
    },
};

const PAGES = [
    { path: '/', name: 'home', trend: homeDuration, weight: 50 },
    { path: '/cennik', name: 'pricing', trend: pricingDuration, weight: 30 },
    { path: '/blog', name: 'blog', trend: blogDuration, weight: 20 },
];

function pickWeightedPage() {
    const roll = Math.random() * 100;
    let cumulative = 0;
    for (const page of PAGES) {
        cumulative += page.weight;
        if (roll < cumulative) {
            return page;
        }
    }
    return PAGES[0];
}

export default function () {
    const page = pickWeightedPage();

    const res = http.get(`${BASE_URL}${page.path}`, {
        headers: htmlHeaders,
        tags: { page: page.name },
    });

    const success = check(res, {
        [`${page.name}: status 200`]: (r) => r.status === 200,
        [`${page.name}: body not empty`]: (r) => r.body && r.body.length > 0,
        [`${page.name}: response time < 1s`]: (r) => r.timings.duration < 1000,
    });

    page.trend.add(res.timings.duration);
    errorRate.add(!success);

    // Simulate user think time: 1-3 seconds between page views
    sleep(Math.random() * 2 + 1);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data, { indent: '  ', enableColors: true }),
        '/results/landing-summary.json': JSON.stringify(data, null, 2),
    };
}

// k6 built-in
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';
