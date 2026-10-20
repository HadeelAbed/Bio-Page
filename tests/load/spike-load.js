/**
 * ---------------------------------------------------------------------------
 * tests/load/spike-load.js — Scenario D: SPIKE TEST
 * ---------------------------------------------------------------------------
 * PURPOSE: simulate a sudden traffic burst (e.g. a link shared on social
 * media): steady low load → jump to SPIKE_VUS for 30 s → back to baseline.
 * Run ONLY after gradual stages (incl. the spike target level) passed.
 *
 * CONFIG via -e:
 *   SPIKE_VUS  burst size (default 500 — lower it to the last PASSED stage
 *              if 500 has not been proven safe yet)
 *
 * THRESHOLDS: error rate stays strict (<1%); latency ceiling is relaxed to
 * p95<1500 ms (bio) because a 30-second burst MAY queue briefly — what must
 * NOT happen is errors, and the app must recover right after the spike
 * (verified by the post-spike tail stage + manual follow-up smoke run).
 * ---------------------------------------------------------------------------
 */
import { SPIKE_THRESHOLDS, publicMix } from './lib/helpers.js';

const SPIKE_VUS = Number(__ENV.SPIKE_VUS || 500);

export const options = {
    stages: [
        { duration: '1m', target: 10 },        // baseline
        { duration: '30s', target: SPIKE_VUS }, // sudden burst
        { duration: '1m', target: 10 },         // recovery (errors here = failed test)
        { duration: '20s', target: 0 },         // wind down
    ],
    thresholds: SPIKE_THRESHOLDS,
};

export default function () {
    publicMix();
}
