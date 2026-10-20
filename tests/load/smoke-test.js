/**
 * ---------------------------------------------------------------------------
 * tests/load/smoke-test.js — Scenario A: SMOKE TEST (1–5 virtual users)
 * ---------------------------------------------------------------------------
 * PURPOSE: verify the whole harness BEFORE any load: server reachable,
 * public routes healthy, checks/thresholds wired correctly, results export
 * working. Read-only public routes only (safety rule).
 *
 * RUN (from the project root, after the staging server is up — see README):
 *   k6 run tests/load/smoke-test.js --summary-export tests/load/results/baseline/smoke.json
 * ---------------------------------------------------------------------------
 */
import { BASE_THRESHOLDS, publicMix } from './lib/helpers.js';

export const options = {
    vus: 3,            // 1–5 VUs per plan
    duration: '45s',
    thresholds: {
        // Smoke must be near-perfect: any failure = fix before loading.
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{page:bio}': ['p(95)<500'],
        'http_req_duration{page:auth}': ['p(95)<800'],
        'http_req_duration': ['p(95)<1500'],
    },
    // Include p99 in the exported summary (Phase 4 metrics).
    summaryExport: __ENV.SUMMARY_EXPORT || null,
};

export default function () {
    publicMix();
}
