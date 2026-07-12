#Requires -Version 5.1
# Windows + XAMPP — queue workeri i scheduleri za oba sustava.
# Obicno se pokrece preko start-dev.ps1, ali moze i samostalno.

[CmdletBinding()]
param(
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$AdminRoot = "C:\Users\zoran.havriluk\multi-tenant console",
    [string]$SaasRoot = "C:\Users\zoran.havriluk\udruga-siletici\udruga-saas",
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $PhpPath)) {
    throw "PHP nije pronaden na: $PhpPath. Prilagodi -PhpPath."
}

foreach ($path in @($AdminRoot, $SaasRoot)) {
    if (-not (Test-Path (Join-Path $path "artisan"))) {
        throw "Laravel nije pronaden na: $path"
    }
}

function Start-Worker {
    param(
        [string]$Name,
        [string]$Root
    )

    $cmd = "cd `"$Root`"; & `"$PhpPath`" artisan queue:work database --sleep=3 --tries=3"
    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        $cmd
    ) -WindowStyle Minimized | Out-Null

    Write-Host "Pokrenut queue worker: $Name"
}

function Start-Scheduler {
    param(
        [string]$Name,
        [string]$Root
    )

    $cmd = @"
while (`$true) {
    Set-Location '$Root'
    & '$PhpPath' artisan schedule:run --no-interaction
    Start-Sleep -Seconds 60
}
"@

    Start-Process powershell -ArgumentList @(
        "-NoExit",
        "-Command",
        $cmd
    ) -WindowStyle Minimized | Out-Null

    Write-Host "Pokrenut scheduler: $Name"
}

Write-Host "Pokrecem pozadinske procese (minimizirani PowerShell prozori)..."
Write-Host ""

Start-Worker -Name "admin-console" -Root $AdminRoot
Start-Scheduler -Name "admin-console" -Root $AdminRoot
Start-Worker -Name "udruga-saas" -Root $SaasRoot
Start-Scheduler -Name "udruga-saas" -Root $SaasRoot

Write-Host ""
Write-Host "Workeri su pokrenuti. Za zaustavljanje zatvori cetiri minimizirana prozora."
