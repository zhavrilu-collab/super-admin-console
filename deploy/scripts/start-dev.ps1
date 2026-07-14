#Requires -Version 5.1
<#
.SYNOPSIS
    Pokrece lokalni dev stack (XAMPP): oba Laravel appa + queue/scheduler.

.DESCRIPTION
    Otvara dva PowerShell prozora s `artisan serve` (SaaS :8000, Admin :8001)
    i pokrece pozadinske queue workere + schedulere preko windows-xampp-workers.ps1.

.PARAMETER Migrate
    Pokrece `php artisan migrate` na oba projekta prije starta.

.PARAMETER SkipWorkers
    Ne pokrece queue workere i schedulere (samo HTTP serveri).

.PARAMETER WorkersOnly
    Pokrece samo workere/schedulere - korisno ako su serveri vec aktivni.

.EXAMPLE
    .\start-dev.ps1

.EXAMPLE
    .\start-dev.ps1 -Migrate

.EXAMPLE
    .\start-dev.ps1 -WorkersOnly
#>
[CmdletBinding()]
param(
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$AdminRoot = "C:\Users\zoran.havriluk\multi-tenant console",
    [string]$SaasRoot = "C:\Users\zoran.havriluk\udruga-siletici\udruga-saas",
    [string]$SmbRoot = "C:\Users\zoran.havriluk\smb-saas",
    [switch]$Migrate,
    [switch]$SkipWorkers,
    [switch]$WorkersOnly
)

$ErrorActionPreference = "Stop"
$WorkersScript = Join-Path $PSScriptRoot "windows-xampp-workers.ps1"

function Test-DevPrerequisites {
    if (-not (Test-Path $PhpPath)) {
        throw "PHP nije pronaden na: $PhpPath. Prilagodi -PhpPath."
    }

    foreach ($project in @(
        @{ Name = "Admin konzola"; Path = $AdminRoot },
        @{ Name = "Udruga SaaS"; Path = $SaasRoot },
        @{ Name = "SMB SaaS"; Path = $SmbRoot }
    )) {
        $artisan = Join-Path $project.Path "artisan"
        if (-not (Test-Path $artisan)) {
            throw "Laravel (artisan) nije pronaden za $($project.Name): $($project.Path)"
        }
    }

    if (-not (Test-Path $WorkersScript)) {
        throw "Workers skripta nije pronadena: $WorkersScript"
    }
}

function Test-PortListening {
    param([int]$Port)

    $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1

    return $null -ne $connection
}

function Invoke-ProjectMigrate {
    param(
        [string]$Name,
        [string]$Root
    )

    Write-Host "Migracija: $Name"
    Push-Location $Root
    try {
        & $PhpPath artisan migrate --force --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw "migrate nije uspio za $Name (exit $LASTEXITCODE)."
        }
    } finally {
        Pop-Location
    }
}

function Start-DevServer {
    param(
        [string]$Name,
        [string]$Root,
        [int]$Port
    )

    if (Test-PortListening -Port $Port) {
        Write-Host "UPOZORENJE: Port $Port je vec zauzet - preskacem $Name server." -ForegroundColor Yellow
        return
    }

    $command = "cd `"$Root`"; & `"$PhpPath`" artisan serve --host=127.0.0.1 --port=$Port"
    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        $command
    ) | Out-Null

    Write-Host "Pokrenut HTTP server: $Name -> http://127.0.0.1:$Port"
}

Write-Host ""
Write-Host "=== Udruga SaaS + Super-Admin - lokalni dev ===" -ForegroundColor Cyan
Write-Host ""

Test-DevPrerequisites

if ($Migrate) {
    Invoke-ProjectMigrate -Name "Udruga SaaS" -Root $SaasRoot
    Invoke-ProjectMigrate -Name "SMB SaaS" -Root $SmbRoot
    Invoke-ProjectMigrate -Name "Admin konzola" -Root $AdminRoot
    Write-Host ""
}

if (-not $WorkersOnly) {
    Start-DevServer -Name "Udruga SaaS" -Root $SaasRoot -Port 8000
    Start-DevServer -Name "SMB SaaS" -Root $SmbRoot -Port 8002
    Start-DevServer -Name "Admin konzola" -Root $AdminRoot -Port 8001
    Write-Host ""
}

if (-not $SkipWorkers) {
    & $WorkersScript -PhpPath $PhpPath -AdminRoot $AdminRoot -SaasRoot $SaasRoot
}

Write-Host ""
Write-Host "Gotovo." -ForegroundColor Green
Write-Host ""
Write-Host "  Udruga SaaS:      http://127.0.0.1:8000"
Write-Host "  SMB SaaS:         http://127.0.0.1:8002"
Write-Host "  Admin konzola:    http://127.0.0.1:8001"
Write-Host "  Admin prijava:    admin@example.com / password"
Write-Host ""
Write-Host "Za zaustavljanje zatvori otvorene PowerShell prozore (serve + workeri)."
Write-Host ""
