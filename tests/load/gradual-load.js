/**
 * ---------------------------------------------------------------------------
 * tests/load/gradual-load.js — Scenario B: GRADUAL LOAD (one stage per run)
 * ---------------------------------------------------------------------------
 * PURPOSE: ramp to a target VU count, hold it, ramp down. The PowerShell
 * runner (run-gradual.ps1) invokes this file ONCE PER STAGE
 * (10 → 100 → 250 → 500 → 1000) and only proceeds when the previous stage
 * passed all thresholds (k6 exit code 0) — enforcing the safety rule
 * "increase concurrency gradually, never jump".
 *
 * CONFIG via -e:
 *   STAGE_VUS       target virtual users            (default 10)
 *   STAGE_DURATION  hold time after ramp-up         (default 60s)
 *   BASE_URL        staging base URL                (default http://127.0.0.1:8080)
 *
 * NOTE: virtual users ≠ requests per second. Each VU issues ONE request at a
 * time and then thinks for 2–6 s, so throughput = VUs / (latency + think).
 * ---------------------------------------------------------------------------
 */
import { BASE_THRESHOLDS, publicMix } from './lib/helpers.js';

const VUS = Number(__ENV.STAGE_VUS || 10);
const HOLD = __ENV.STAGE_DURATION || '60s';

export const options = {
    stages: [
        { duration: '15s', target: VUS },   // ramp up
        { duration: HOLD, target: VUS },    // hold at target
        { duration: '15s', target: 0 },     // ramp down
    ],
    thresholds: BASE_THRESHOLDS,
    summaryTrendStats: __ENV.SUMMARY_TREND_STATS
        ? __ENV.SUMMARY_TREND_STATS.split(',')
        : undefined,
};

export default function () {
    publicMix();
}
