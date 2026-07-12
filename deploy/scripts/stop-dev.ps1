#Requires -Version 5.1
<#
.SYNOPSIS
    Zaustavlja lokalne dev servere na portovima 8000 i 8001.
#>
[CmdletBinding()]
param(
    [int[]]$Ports = @(8000, 8001),
)

$ErrorActionPreference = "SilentlyContinue"

Write-Host "Zaustavljam procese na portovima: $($Ports -join ', ')" -ForegroundColor Cyan

foreach ($port in $Ports) {
    $connections = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
    $pids = $connections | Select-Object -ExpandProperty OwningProcess -Unique

    foreach ($pid in $pids) {
        $process = Get-Process -Id $pid -ErrorAction SilentlyContinue
        if ($null -eq $process) {
            continue
        }

        Write-Host "  Port $port -> PID $pid ($($process.ProcessName))"
        Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
    }
}

Write-Host ""
Write-Host "Gotovo. Queue workere i schedulere i dalje zatvori rucno ako su pokrenuti." -ForegroundColor Yellow
Write-Host ""
