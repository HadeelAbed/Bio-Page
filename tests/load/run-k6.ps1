<#
.SYNOPSIS
  Generic k6 wrapper for background/long runs: executes k6, captures the full
  console output to a log file and records the exit code in a side-car file
  (so callers can poll instead of blocking).

.EXAMPLE
  Start-Process powershell -ArgumentList @(
      '-NoProfile','-ExecutionPolicy','Bypass',
      '-File','tests/load/run-k6.ps1',
      '-K6Args','run,tests/load/smoke-test.js,--summary-export,tests/load/results/baseline/smoke.json'
  ) -PassThru

  # then poll:  Get-Content tests/load/results/baseline/smoke.exit
#>
param(
    [string]$K6 = $(if ($env:K6_BIN) { $env:K6_BIN } else { 'k6' }),
    [string]$K6Args = '',
    [string]$LogFile = "$env:TEMP\k6-run.log",
    [string]$ExitFile = "$env:TEMP\k6-run.exit"
)

# K6Args arrives as ONE string of comma-free args OR as an array via -File …
# We accept a simple space/comma separated string and split it.
$argList = @()
foreach ($a in ($K6Args -split '[,;]|(?<=\s)(?=-)')) {
    $t = $a.Trim()
    if ($t -ne '') { $argList += $t }
}
if ($argList.Count -eq 0) { $argList = @('version') }

& $K6 @argList *> $LogFile
$code = $LASTEXITCODE
Set-Content -Path $ExitFile -Value $code
exit $code
