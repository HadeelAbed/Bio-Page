# Load Testing Suite — Bio Page

k6-based load-testing suite for the Bio Page Laravel application. Answers the
question: **can the app serve 1,000 concurrent virtual users without slowing
down, erroring, or crashing?** — and documents exactly where the limit is.

* Tool: **k6 v2.x** (Grafana) — installed OUTSIDE this repo (see Install).
* Scenarios: smoke → gradual → sustained → spike → authenticated flows.
* Everything lives in `tests/load/` and is never loaded by the application
  (`tests/` is not part of the runtime autoloader; k6 is not a composer/npm dep).

---

## 1. Test environment (what the tests hit)

| Piece | Value | Why |
|---|---|---|
| Target URL | `http://127.0.0.1:8080` | Local **staging vhost**: Apache 2.4 + mod_php (mpm_winnt, **150 worker threads**). Unlike `php artisan serve` (single-threaded on Windows), Apache can actually process requests concurrently — otherwise the test measures a dev-server bottleneck, not the app. |
| Database | `bio_page_loadtest` (MariaDB 10.4) | **Isolated** MySQL database pinned to the vhost via `SetEnv`. Load-test writes never touch the dev DB (`bio_page`) or any production data. |
| Test accounts | `loadtest01`..`loadtest20` / `loadtestpass123` | 20 dedicated accounts (created by `setup-testdata.php`), one per VU — never thousands. |
| E-mail | `MAIL_MAILER=log` | No real emails/notifications can leave the machine. |
| Metrics endpoint | `http://127.0.0.1:8080/server-status?auto` | mod_status: Busy/Idle workers (PHP worker saturation), requests. |

> **k6 measures HTTP server behavior only.** Browser-side work (Tailwind CDN
> script, Google Fonts, FontAwesome, Alpine.js, image paint) is NOT included —
> those load from third-party CDNs on the client. Server metrics below are
> the server's own latency (TTFB + HTML transfer).

### Staging server — start / stop

```powershell
# 1. MySQL (XAMPP)
Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -WindowStyle Hidden

# 2. Apache staging server (after the vhost config was added — one time)
Start-Process 'C:\xampp\apache\bin\httpd.exe' -WorkingDirectory 'C:\xampp\apache\bin' -WindowStyle Hidden

# 3. (optional) warm the app + confirm DB isolation
curl.exe -s -o NUL -w "%{http_code}" http://127.0.0.1:8080/hadeel     # expect 200
curl.exe -s -o NUL -w "%{http_code}" http://127.0.0.1:8080/anything-that-should-404-in-loadtest-db

# STOP (end of test window)
Stop-Process -Name httpd -Force     # stop Apache
# mysqld: stop via XAMPP control panel or Stop-Process -Name mysqld
```

Staging config (already applied to XAMPP, documented here for reproducibility):
`httpd.conf` → `Listen 8080`; `conf/extra/httpd-vhosts.conf` →
`<VirtualHost *:8080>` with `DocumentRoot .../public`, `SetEnv DB_DATABASE
bio_page_loadtest`, `mod_status` Location (via stub-file Alias so Laravel's
`.htaccess` rewrite doesn't swallow it).

---

## 2. Install k6 (kept separate from app dependencies)

```powershell

---

## 4. Scenarios & exact commands

Run all commands **from the project root**. `$env:K6_BIN` = path to k6.exe
(from Install). **Never run two load tests at the same time.**

### A. Smoke test (1–5 VUs) — ALWAYS RUN FIRST

```powershell
& $env:K6_BIN run tests/load/smoke-test.js `
    --summary-trend-stats "avg,min,med,max,p(90),p(95),p(99),count" `
    --summary-export tests/load/results/baseline/smoke.json
```
Passes when: error rate <1%, checks >99%, bio p95 <500 ms. If smoke fails —
fix the environment/app and re-run; do NOT proceed to load stages.

### B. Gradual load (10 → 100 → 250 → 500 → 1000 VUs)

```powershell
pwsh -File tests/load/run-gradual.ps1 `
    -K6 $env:K6_BIN `
    -BaseUrl "http://127.0.0.1:8080" `
    -ResultsDir tests/load/results/baseline
```
The runner **halts automatically** on the first stage whose thresholds fail
(k6 exit ≠ 0). Each stage: 15 s ramp-up → 60 s hold → 15 s ramp-down, 10 s
cool-down between stages. Results: `results/baseline/gradual-<n>.json`.

Single stage by hand:
```powershell
& $env:K6_BIN run tests/load/gradual-load.js -e STAGE_VUS=100 -e STAGE_DURATION=60s `
    --summary-export tests/load/results/baseline/manual-100.json
```

### C. Sustained load (highest safe concurrency, minutes)

```powershell
& $env:K6_BIN run tests/load/sustained-load.js `
    -e STAGE_VUS=250 -e STAGE_DURATION=5m `
    --summary-trend-stats "avg,min,med,max,p(90),p(95),p(99),count" `
    --summary-export tests/load/results/baseline/sustained-250.json
```
Use the highest stage that PASSED in B (default shown: 250).

### D. Spike test (only after gradual passes at the spike level)

```powershell
& $env:K6_BIN run tests/load/spike-load.js -e SPIKE_VUS=500 `
    --summary-export tests/load/results/baseline/spike-500.json
```
10 VUs baseline → 30 s burst at SPIKE_VUS → back to 10 VU recovery.
Error rate stays <1% throughout; recovery tail must stay healthy.

### E. Authenticated flows (isolated accounts + write ops)

```powershell

---

## 6. Metrics collected (Phase 4 mapping)

| Required metric | k6 source | Extra source |
|---|---|---|
| Throughput (req/s) | `http_reqs` rate in summary | Apache `Total Accesses` (server-status) |
| Median p50 | `http_req_duration` → `med` (summary-trend-stats) | — |
| p95 / p99 | `p(95)` / `p(99)` per `page` tag | — |
| HTTP error rate / failed checks | `http_req_failed`, `checks` | Apache `bio-page-loadtest-error.log` |
| Timeouts / connection failures | `http_req_failed` breakdown + k6 console “non-2xx/3xx” lines | — |
| CPU / memory | — | `monitor-resources.ps1` (httpd, mysqld) |
| PHP worker saturation | — | mod_status `BusyWorkers` (of 150) |
| MySQL connections / slow queries / locks | — | `Threads_connected`, `Threads_running`, `Max_used_connections`, `Slow_queries` (>1 s), `SHOW ENGINE INNODB STATUS` lock section if needed |
| Cache hit rate | **not measured** — Laravel's database cache store exposes no hit-rate metric; adding monitoring (e.g. Pulse/Redis) is out of scope per “no new dependencies without justification” | documented limitation |
| Max concurrency within thresholds | highest passed stage in `run-gradual.ps1` | report |

Results layout: `tests/load/results/<baseline|optimized>/…json|.csv`
(results dirs are git-ignored except the directory itself).

## 7. Files

| File | Role |
|---|---|
| `lib/helpers.js` | Base URL, thresholds, traffic mix, think times, CSRF/form helpers |
| `smoke-test.js` | Scenario A — 3 VUs, 45 s, public read-only |
| `gradual-load.js` | Scenario B — one stage per invocation (env-driven) |
| `run-gradual.ps1` | Gated stage runner (halts on first failed stage) |
| `sustained-load.js` | Scenario C — long hold at highest safe VUs |
| `spike-load.js` | Scenario D — burst + recovery |
| `authenticated-flows.js` | Scenario E — login/dashboard/write with isolated accounts |
| `monitor-resources.ps1` | CPU/RAM/workers/DB-connection sampler |
| `setup-testdata.php` | Idempotent creator of loadtest01..20 (refuses non-staging DB) |
| `results/` | k6 summary exports + resource CSVs (git-ignored artifacts) |

## 8. Git / dependency policy

* k6 binary, results and staging-server config live OUTSIDE the app runtime;
  only scripts + docs under `tests/load/` are committed.
* No changes to `composer.json`, `package.json` or application runtime deps.
* Do not push until the reviewer approves the results (per project policy).

& $env:K6_BIN run tests/load/authenticated-flows.js `
    -e AUTH_VUS=20 -e STAGE_DURATION=3m `
    --summary-trend-stats "avg,min,med,max,p(90),p(95),p(99),count" `
    --summary-export tests/load/results/baseline/authenticated.json
```
20 VUs = 20 dedicated accounts. Each VU: login once → dashboard reads +
representative profile/link PUTs (own data only) → 6–15 s think time.

### Resource sampling (second shell, during any stage)

```powershell
pwsh -File tests/load/monitor-resources.ps1 -Seconds 90 `
    -OutFile tests/load/results/baseline/resources-stage-100.csv
```
Samples Apache CPU/MB, Busy/Idle workers (out of 150), MySQL CPU/MB,
Threads_connected/Running (max_connections=151), Slow_queries (log ON, >1 s).

### STOPPING A RUN

* **k6 in foreground:** `Ctrl+C` — k6 aborts gracefully and prints the summary
  (partial results are still valid; mark the stage as aborted in the report).
* **k6 as a background process:** `Stop-Process -Id <k6 pid> -Force`.
* **Runner script:** Ctrl+C or `Stop-Process -Name powershell,pwsh` for the
  runner only (do NOT stop Apache mid-flight unless the app is erroring).
* **Emergency stop condition:** widespread 5xx/timeouts in the console, latency
  blowing past thresholds, or BusyWorkers ≈ 150 / MySQL conns ≈ 151 → stop the
  run, record the level, and treat it as the environment's tested ceiling.

---

## 5. Thresholds & stop conditions (defined BEFORE each run)

| Metric | Smoke | Gradual / Sustained | Spike | Rationale |
|---|---|---|---|---|
| `http_req_failed` (HTTP errors, timeouts, conn failures) | <1% | <1% | <1% | Hard safety bar from the test plan |
| `checks` (status + content assertions) | >99% | >99% | >99% | Wrong/slow responses counted as failures |
| p95 bio pages | <500 ms | <500 ms (sustained also p99<1500) | <1500 ms | Target from plan; relaxed ONLY for the 30 s burst |
| p95 auth pages | <800 ms | <800 ms | — | Includes session cookie write |
| p95 overall | <1500 ms | <1500 ms | <2500 ms | Sanity ceiling |

**Threshold applicability note:** the 500 ms bio p95 target is realistic on this
staging box at low/mid concurrency (warm latency ≈ 120–150 ms single-request).
If a stage fails *only* on latency while error rate stays clean, record the
concurrency level where p95 crosses 500 ms as the **latency ceiling** — do not
loosen the threshold silently. Every deviation must be documented in the report.

Hard stop conditions (any one → stop the run immediately):
1. Error rate visibly climbing toward 1% (console shows failing checks/5xx).
2. p95 latency several-fold above target with growth trend between samples.
3. Apache BusyWorkers pegged at 150 or MySQL `Max_used_connections` near 151.
4. Application returns 5xx pages or connection refusals (app/thread death).

# Windows x64 (version pinned in the suite README; v2.3.0 used for these results)
$zip = "$env:TEMP\k6.zip"
Invoke-WebRequest -Uri "https://github.com/grafana/k6/releases/download/v2.3.0/k6-v2.3.0-windows-amd64.zip" -OutFile $zip
Expand-Archive $zip "$env:TEMP\k6-dist" -Force
$env:K6_BIN = "$env:TEMP\k6-dist\k6-v2.3.0-windows-amd64\k6.exe"
& $env:K6_BIN version        # sanity check
```

Add the directory to `PATH` permanently, or pass `-K6 <path>` / `K6_BIN` to the
runner scripts. **No changes to `composer.json`/`package.json` are made.**

---

## 3. Test data setup (once per environment)

```powershell
# From the project root. ALWAYS pins the isolated staging DB:
$env:DB_DATABASE='bio_page_loadtest'; php tests/load/setup-testdata.php; $env:DB_DATABASE=$null

# Full rebuild of the staging DB if it ever gets messy:
$env:DB_DATABASE='bio_page_loadtest'; php artisan migrate:fresh --seed --force; $env:DB_DATABASE=$null
$env:DB_DATABASE='bio_page_loadtest'; php tests/load/setup-testdata.php; $env:DB_DATABASE=$null
```
