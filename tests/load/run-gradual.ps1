<#
.SYNOPSIS
  Scenario B runner � runs gradual-load.js one stage at a time and STOPS
  the progression as soon as a stage fails its thresholds.

.DESCRIPTION
  Safety policy enforced by this script:
    * concurrency only ever increases after the previous level proved healthy
      (k6 exit code 0 = every threshold passed),
    * stages run SEQUENTIALLY with a cool-down between them (never two
      high-load tests at once),
    * every stage's summary is exported to JSON for the final report.

.EXAMPLE
  pwsh -File tests/load/run-gradual.ps1 -ResultsDir tests/load/results/baseline

  # custom k6 binary + target:
  pwsh -File tests/load/run-gradual.ps1 -K6 "C:\tools\k6\k6.exe" -BaseUrl "http://127.0.0.1:8080"
#>
param(
    [string]$K6 = $(if ($env:K6_BIN) { $env:K6_BIN } else { 'k6' }),
    [string]$BaseUrl = 'http://127.0.0.1:8080',
    [int[]]$Stages = @(10, 100, 250, 500, 1000),
    [string]$StageDuration = '60s',
    [string]$ResultsDir = "$PSScriptRoot\results\baseline",
    [int]$CoolDownSec = 10
)

$ErrorActionPreference = 'Stop'
$TrendStats = 'avg,min,med,max,p(90),p(95),p(99),count'
New-Item -ItemType Directory -Force -Path $ResultsDir | Out-Null

# Fail fast if the target is not answering BEFORE loading it.
try {
    $probe = Invoke-WebRequest -Uri "$BaseUrl/hadeel" -UseBasicParsing -TimeoutSec 10
    if ($probe.StatusCode -ne 200) { throw "status $($probe.StatusCode)" }
} catch {
    Write-Host "ABORT: staging target $BaseUrl is not healthy ($($_.Exception.Message))." -ForegroundColor Red
    Write-Host "Start it first -> see tests/load/README.md (staging server section)." -ForegroundColor Yellow
    exit 2
}

foreach ($vus in $Stages) {
    $out = Join-Path $ResultsDir "gradual-$vus.json"
    Write-Host ""
    Write-Host "=== STAGE: $vus virtual users (hold $StageDuration) ===" -ForegroundColor Cyan

    & $K6 run `
        --summary-export $out `
        --summary-trend-stats $TrendStats `
        -e "BASE_URL=$BaseUrl" `
        -e "STAGE_VUS=$vus" `
        -e "STAGE_DURATION=$StageDuration" `
        "$PSScriptRoot\gradual-load.js"

    $code = $LASTEXITCODE
    if ($code -ne 0) {
        Write-Host ""
        Write-Host "STAGE $vus VUs FAILED (k6 exit code $code). Progression halted." -ForegroundColor Red
        Write-Host "Fix/re-measure before attempting higher concurrency." -ForegroundColor Yellow
        exit $code
    }

    Write-Host "Stage $vus PASSED. Cooling down ${CoolDownSec}s" -ForegroundColor Green
    Start-Sleep -Seconds $CoolDownSec
}

Write-Host ""
Write-Host "All stages passed: $($Stages -join ' -> ') VUs." -ForegroundColor Green
Write-Host "Next: sustained-load.js at the highest safe level, then spike-load.js." -ForegroundColor Yellow
exit 0
