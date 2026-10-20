/**
 * ---------------------------------------------------------------------------
 * tests/load/lib/helpers.js � shared helpers for the Bio Page k6 suite.
 * ---------------------------------------------------------------------------
 */
import { check, sleep } from 'k6';
import http from 'k6/http';

export const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8080';

export const BASE_THRESHOLDS = {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    'http_req_duration{page:bio}': ['p(95)<500'],
    'http_req_duration{page:auth}': ['p(95)<800'],
    'http_req_duration': ['p(95)<1500'],
};

export const SPIKE_THRESHOLDS = {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    'http_req_duration{page:bio}': ['p(95)<1500'],
    'http_req_duration': ['p(95)<2500'],
};

function rand(min, max) {
    return min + Math.random() * (max - min);
}

export function think(min, max) {
    sleep(rand(min, max));
}
const TAIL_USERS = [
    'loadtest01', 'loadtest02', 'loadtest03', 'loadtest04', 'loadtest05',
    'loadtest06', 'loadtest07', 'loadtest08', 'loadtest09', 'loadtest10',
    'loadtest11', 'loadtest12', 'loadtest13', 'loadtest14', 'loadtest15',
    'loadtest16', 'loadtest17', 'loadtest18', 'loadtest19', 'loadtest20',
];

export function pickBioUser() {
    if (Math.random() < 0.6) {
        return 'hadeel';
    }
    return TAIL_USERS[Math.floor(Math.random() * TAIL_USERS.length)];
}

export function tailUsers() {
    return TAIL_USERS.slice();
}
export function getBioPage() {
    const username = pickBioUser();
    const res = http.get(`${BASE_URL}/${username}`, { tags: { page: 'bio' }, redirects: 0 });
    check(res, {
        'bio status is 200': (r) => r.status === 200,
        'bio has page title': (r) => r.body !== null && r.body.indexOf('<title>') !== -1,
    }, { page: 'bio' });
    return res;
}

export function getAuthPage(path) {
    const res = http.get(`${BASE_URL}${path}`, { tags: { page: 'auth' } });
    check(res, {
        'auth page status is 200': (r) => r.status === 200,
        'auth page has form + csrf': (r) =>
            r.body !== null && r.body.indexOf('<form') !== -1 && r.body.indexOf('_token') !== -1,
    }, { page: 'auth' });
    return res;
}

export function getHome() {
    const res = http.get(`${BASE_URL}/`, { tags: { page: 'home' }, redirects: 0 });
    check(res, {
        'home page status is 200': (r) => r.status === 200,
        'home page content is present': (r) => r.body !== null && r.body.indexOf('رابط واحد يجمع') !== -1,
    }, { page: 'home' });
    return res;
}

export function getDashboardGuest() {
    const res = http.get(`${BASE_URL}/dashboard`, { tags: { page: 'guest' }, redirects: 0 });
    check(res, { 'guest dashboard redirects': (r) => r.status === 302 }, { page: 'guest' });
    return res;
}

export function publicMix() {
    const r = Math.random();
    if (r < 0.80) { getBioPage(); }
    else if (r < 0.90) { getAuthPage('/login'); }
    else if (r < 0.95) { getAuthPage('/register'); }
    else if (r < 0.98) { getHome(); }
    else { getDashboardGuest(); }
    think(2, 6);
}

export function extractCsrf(html) {
    if (!html) { return null; }
    const a = 'name="_token" value="';
    let i = html.indexOf(a);
    if (i === -1) {
        const b = 'value="';
        i = html.indexOf(b);
        if (i === -1) { return null; }
        i += b.length;
    } else {
        i += a.length;
    }
    const end = html.indexOf('"', i);
    return end === -1 ? null : html.slice(i, end);
}
export function formEncode(obj) {
    const parts = [];
    for (const k in obj) {
        if (Object.prototype.hasOwnProperty.call(obj, k)) {
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]));
        }
    }
    return parts.join('&');
}

export const FORM_HEADERS = { 'Content-Type': 'application/x-www-form-urlencoded' };
