<#
.SYNOPSIS
  Samples CPU/memory of Apache + MariaDB, Apache worker saturation (mod_status)
  and MySQL connection stats while a load test runs. Output: console table
  and optional CSV for the report.

.DESCRIPTION
  Run it BEFORE/CONCURRENTLY with a k6 stage (in a second shell):
    pwsh -File tests/load/monitor-resources.ps1 -Seconds 90 -OutFile tests/load/results/baseline/resources-stage-100.csv

  Sampled every -IntervalSec seconds:
    * Apache httpd: total CPU seconds delta/s, working-set MB
    * MySQL  mysqld: CPU delta/s, working-set MB
    * mod_status   : BusyWorkers / IdleWorkers (PHP worker saturation)
    * MySQL status : Threads_connected, Threads_running, Max_used_connections,
                     Slow_queries delta  (vs max_connections=151)
#>
param(
    [int]$Seconds = 60,
    [int]$IntervalSec = 2,
    [string]$StatusUrl = 'http://127.0.0.1:8080/server-status?auto',
    [string]$MySql = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$OutFile = ''
)

$ErrorActionPreference = 'SilentlyContinue'
$end = (Get-Date).AddSeconds($Seconds)
$rows = @()
$prevHttpdCpu = 0; $prevMysqlCpu = 0; $prevSlow = 0; $prevTime = Get-Date

function Get-MySqlStatus {
    param([string]$exe)
    $raw = & $exe -u root -N -e "SHOW STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Max_used_connections','Slow_queries');"
    $h = @{}
    foreach ($line in $raw) {
        $parts = ($line -split "`t")
        if ($parts.Count -eq 2) { $h[$parts[0]] = [double]$parts[1] }
    }
    return $h
}

Write-Host "Sampling every ${IntervalSec}s for ${Seconds}s ..." -ForegroundColor Cyan

while ((Get-Date) -lt $end) {
    $httpd = Get-Process httpd -ErrorAction SilentlyContinue
    $mysqld = Get-Process mysqld -ErrorAction SilentlyContinue

    $httpdCpu = ($httpd | Measure-Object -Property CPU -Sum).Sum
    $httpdMem = ($httpd | Measure-Object -Property PM -Sum).Sum / 1MB
    $mysqlCpu = ($mysqld | Measure-Object -Property CPU -Sum).Sum
    $mysqlMem = ($mysqld | Measure-Object -Property PM -Sum).Sum / 1MB

    $now = Get-Date
    $dt = ($now - $prevTime).TotalSeconds
    if ($dt -le 0) { $dt = 0.001 }

    $busy = ''; $idle = ''
    $st = (Invoke-WebRequest -Uri $StatusUrl -UseBasicParsing -TimeoutSec 3).Content -split "`n"
    foreach ($l in $st) {
        if ($l -match '^BusyWorkers:\s+(\d+)') { $busy = [int]$Matches[1] }
        if ($l -match '^IdleWorkers:\s+(\d+)') { $idle = [int]$Matches[1] }
    }

    $db = Get-MySqlStatus -exe $MySql
    $slow = $db['Slow_queries']; if ($null -eq $slow) { $slow = 0 }

    $rows += [pscustomobject]@{
        Time              = $now.ToString('HH:mm:ss')
        HttpdCpuPerSec    = [math]::Round((($httpdCpu - $prevHttpdCpu) / $dt), 2)
        HttpdMemMB        = [math]::Round($httpdMem, 0)
        BusyWorkers       = $busy
        IdleWorkers       = $idle
        MysqlCpuPerSec    = [math]::Round((($mysqlCpu - $prevMysqlCpu) / $dt), 2)
        MysqlMemMB        = [math]::Round($mysqlMem, 0)
        ThreadsConnected  = $db['Threads_connected']
        ThreadsRunning    = $db['Threads_running']
        MaxUsedConns      = $db['Max_used_connections']
        SlowQueriesTotal  = $slow
    }

    $prevHttpdCpu = $httpdCpu; $prevMysqlCpu = $mysqlCpu; $prevTime = $now
    Start-Sleep -Seconds $IntervalSec
}

$rows | Format-Table -AutoSize

if ($rows.Count -gt 0) {
    Write-Host "Peak: BusyWorkers=$((($rows | Measure-Object -Property BusyWorkers -Maximum).Maximum)) / 150 threads, " -NoNewline
    Write-Host "MaxUsedConnections=$((($rows | Measure-Object -Property MaxUsedConns -Maximum).Maximum)) / 151, " -NoNewline
    Write-Host "SlowQueriesDelta=$((($rows | Measure-Object -Property SlowQueriesTotal -Maximum).Maximum) - ($rows[0].SlowQueriesTotal))"
    if ($OutFile) {
        $dir = Split-Path $OutFile -Parent
        if ($dir) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
        $rows | Export-Csv -Path $OutFile -NoTypeInformation
        Write-Host "CSV written: $OutFile" -ForegroundColor Green
    }
}
