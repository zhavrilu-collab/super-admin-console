#Requires -Version 5.1
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root ".env.docker"
$XamppScript = Join-Path $Root "scripts\windows-xampp-workers.ps1"

function Write-DockerMissingHelp {
    Write-Host ""
    Write-Host "Docker nije dostupan ili Docker Desktop nije pokrenut." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opcija A — Pokreni Docker Desktop, pričekaj da status bude Running, pa ponovi:" -ForegroundColor Cyan
    Write-Host '  .\scripts\bootstrap.ps1'
    Write-Host ""
    Write-Host "Opcija B — Bez Dockera, koristi XAMPP (preporuka za lokalni dev):" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  # Terminal 1"
    Write-Host '  cd C:\Users\zoran.havriluk\udruga-siletici\udruga-saas'
    Write-Host '  C:\xampp\php\php.exe artisan serve --port=8000'
    Write-Host ""
    Write-Host "  # Terminal 2"
    Write-Host '  cd "C:\Users\zoran.havriluk\multi-tenant console"'
    Write-Host '  C:\xampp\php\php.exe artisan serve --port=8001'
    Write-Host ""
    Write-Host "  # Terminal 3 — queue + scheduler"
    Write-Host "  .\scripts\windows-xampp-workers.ps1"
    Write-Host ""
}

function Test-DockerReady {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        return $false
    }

    docker info *> $null
    return $LASTEXITCODE -eq 0
}

function Invoke-DockerCompose {
    param([string[]]$Arguments)

    & docker compose --env-file .env.docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose $($Arguments -join ' ') nije uspio (exit $LASTEXITCODE)."
    }
}

if (-not (Test-Path $EnvFile)) {
    Copy-Item (Join-Path $Root ".env.docker.example") $EnvFile
    Write-Host "Kreiran deploy/.env.docker"
}

if (-not (Test-DockerReady)) {
    Write-DockerMissingHelp
    exit 1
}

Set-Location $Root

Write-Host "Docker je spreman. Gradim slike..." -ForegroundColor Green

Invoke-DockerCompose @("build")
Invoke-DockerCompose @("up", "-d", "mysql")

Write-Host "Cekam MySQL (20s)..."
Start-Sleep -Seconds 20

Invoke-DockerCompose @("up", "-d")

Write-Host ""
Write-Host "Gotovo." -ForegroundColor Green
Write-Host ""
Write-Host "  Admin konzola: http://localhost:8001"
Write-Host "  Udruga SaaS:   http://localhost:8000"
Write-Host ""
Write-Host "Prvi migrate/seed:"
Write-Host "  docker compose --env-file .env.docker exec admin-php php artisan migrate --force"
Write-Host "  docker compose --env-file .env.docker exec admin-php php artisan db:seed --class=AdminConsoleSeeder"
Write-Host "  docker compose --env-file .env.docker exec saas-php php artisan migrate --force"
Write-Host ""
