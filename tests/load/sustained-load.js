/**
 * ---------------------------------------------------------------------------
 * tests/load/sustained-load.js — Scenario C: SUSTAINED LOAD
 * ---------------------------------------------------------------------------
 * PURPOSE: hold the HIGHEST SAFE concurrency (proven by the gradual stages)
 * for several minutes to expose leaks, lock contention, session/cache table
 * growth, or latency drift that short tests miss.
 *
 * CONFIG via -e:
 *   STAGE_VUS       sustained VUs — set to the highest PASSED gradual stage
 *                   (default 250)
 *   STAGE_DURATION  hold time (default 5m)
 *
 * Run ONLY after run-gradual.ps1 passes for the chosen VU count.
 * ---------------------------------------------------------------------------
 */
import { BASE_THRESHOLDS, publicMix } from './lib/helpers.js';

const VUS = Number(__ENV.STAGE_VUS || 250);
const HOLD = __ENV.STAGE_DURATION || '5m';

export const options = {
    stages: [
        { duration: '30s', target: VUS },   // ramp up
        { duration: HOLD, target: VUS },    // sustained hold (the point of the test)
        { duration: '20s', target: 0 },     // ramp down
    ],
    thresholds: {
        ...BASE_THRESHOLDS,
        // Long run: also demand the error rate stays clean over time and
        // latency does not drift unboundedly at the tail.
        'http_req_duration{page:bio}': ['p(95)<500', 'p(99)<1500'],
    },
};

export default function () {
    publicMix();
}
