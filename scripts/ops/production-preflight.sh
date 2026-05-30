#!/usr/bin/env bash
set -e

echo -e "\033[1;36mStarting Production Deployment Preflight Checks...\033[0m"

echo -e "\n\033[1;33m1. Checking Backend Style (Pint)...\033[0m"
php vendor/laravel/pint/builds/pint --test -v

echo -e "\n\033[1;33m2. Checking Operational Health & Readiness...\033[0m"
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --json
php artisan booking:launch-readiness --target=staging --json

echo -e "\n\033[1;33m3. Checking Staff-Web Build...\033[0m"
cd staff-web
npx tsc --noEmit
npm run build
cd ..

echo -e "\n\033[1;33m4. Checking Customer-Web Build...\033[0m"
cd customer-web
npx tsc --noEmit
npm run build
cd ..

echo -e "\n\033[1;32mProduction Deployment Preflight Checks Passed Successfully!\033[0m"
