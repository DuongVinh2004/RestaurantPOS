<#
.SYNOPSIS
    Run strict production deployment preflight checks (Staging Dry-run Gate)
.DESCRIPTION
    This script evaluates backend, frontend, and operational readiness before production deployment.
#>

$ErrorActionPreference = "Stop"

Write-Host "Starting Production Deployment Preflight Checks..." -ForegroundColor Cyan

Write-Host "`n1. Checking Backend Style (Pint)..." -ForegroundColor Yellow
php vendor/laravel/pint/builds/pint --test -v

Write-Host "`n2. Checking Operational Health & Readiness..." -ForegroundColor Yellow
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json
php artisan booking:launch-readiness --target=staging --json

Write-Host "`n3. Checking Staff-Web Build..." -ForegroundColor Yellow
Push-Location staff-web
npx tsc --noEmit
npm run build
Pop-Location

Write-Host "`n4. Checking Customer-Web Build..." -ForegroundColor Yellow
Push-Location customer-web
npx tsc --noEmit
npm run build
Pop-Location

Write-Host "`nProduction Deployment Preflight Checks Passed Successfully!" -ForegroundColor Green
