import { check, sleep } from 'k6';
import http from 'k6/http';
import { BASE_URL, FORM_HEADERS, extractCsrf, formEncode, tailUsers } from './lib/helpers.js';

const ACCOUNTS = tailUsers();
const ACCOUNT_PASSWORD = 'loadtestpass123';
const VUS = Number(__ENV.AUTH_VUS || 20);
const DURATION = __ENV.STAGE_DURATION || '3m';

export const options = {
    scenarios: {
        dashboard_users: {
            executor: 'constant-vus',
            vus: VUS,
            duration: DURATION,
            exec: 'authFlow',
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        'http_req_duration{page:login}': ['p(95)<1200'],
        'http_req_duration{page:dashboard}': ['p(95)<800'],
        'http_req_duration{page:write}': ['p(95)<1500'],
        'http_req_duration': ['p(95)<1500'],
    },
};

let sessionUser = null;

function accountForVU() {
    const idx = ((__VU || 1) - 1) % ACCOUNTS.length;
    return ACCOUNTS[idx];
}

function login(user) {
    const page = http.get(`${BASE_URL}/login`, { tags: { page: 'login' } });
    const token = extractCsrf(page.body);
    check(page, {
        'login page 200': (r) => r.status === 200,
        'login csrf present': () => token !== null,
    }, { page: 'login' });

    const body = formEncode({
        _token: token,
        email: `${user}@example.test`,
        password: ACCOUNT_PASSWORD,
    });
    const res = http.post(`${BASE_URL}/login`, body, {
        tags: { page: 'login' },
        headers: FORM_HEADERS,
        redirects: 0,
    });
    const ok = check(res, {
        'login accepted (302?dashboard)': (r) =>
            r.status === 302 && String(r.headers['Location'] || r.headers['location']).indexOf('/dashboard') !== -1,
    }, { page: 'login' });
    if (ok) {
        sessionUser = user;
    }
    return ok;
}
function viewDashboard(user) {
    const res = http.get(`${BASE_URL}/dashboard`, { tags: { page: 'dashboard' } });
    const ok = check(res, {
        'dashboard 200': (r) => r.status === 200,
        'dashboard is own page': (r) => r.body !== null && r.body.indexOf('@' + user) !== -1,
    }, { page: 'dashboard' });
    if (!ok) { sessionUser = null; }
    return res;
}

function updateProfile(user, token) {
    const body = formEncode({
        _token: token,
        _method: 'PUT',
        display_name: `Load Test User ${user.slice(-2)}`,
        bio: 'Updated by load test',
        bg_color_1: '#b88c8f',
        bg_color_2: '#ba8b8f',
        text_color: '#1a1a1a',
        'links[0][title]': 'Load Link A',
        'links[0][url]': 'https://example.com/a',
        'links[0][is_active]': '1',
        'links[1][title]': 'Load Link B',
        'links[1][url]': 'https://example.com/b',
        'links[1][is_active]': '1',
    });
    const res = http.post(`${BASE_URL}/dashboard`, body, {
        tags: { page: 'write' },
        headers: FORM_HEADERS,
        redirects: 0,
    });
    const ok = check(res, {
        'update saved (302?dashboard)': (r) =>
            r.status === 302 && String(r.headers['Location'] || r.headers['location']).indexOf('/dashboard') !== -1,
    }, { page: 'write' });
    if (ok) {
        const follow = http.get(`${BASE_URL}/dashboard`, { tags: { page: 'dashboard' } });
        check(follow, { 'post-update dashboard 200': (r) => r.status === 200 }, { page: 'dashboard' });
    } else if (res.status === 419) {
        sessionUser = null;
    }
    return res;
}

export function authFlow() {
    const user = accountForVU();
    if (sessionUser === null) {
        if (!login(user)) { sleep(3); return; }
    }
    if (Math.random() < 0.3) {
        const page = http.get(`${BASE_URL}/dashboard`, { tags: { page: 'dashboard' } });
        const token = extractCsrf(page.body);
        check(page, {
            'dashboard 200': (r) => r.status === 200,
            'dashboard is own page': (r) => r.body !== null && r.body.indexOf('@' + user) !== -1,
        }, { page: 'dashboard' });
        if (token) { updateProfile(user, token); }
    } else {
        viewDashboard(user);
    }
    sleep(6 + Math.random() * 9);
}
