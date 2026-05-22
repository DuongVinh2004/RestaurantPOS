# Verification Commands

This document contains the standard, Windows-friendly commands used to verify the RestaurantPOS repository before a release. Run these in your terminal (PowerShell or Command Prompt) and redirect output to the `docs/verification-evidence/` folder as needed.

## Backend Code Quality & Tests
```powershell
composer verify:select -- --base=origin/main
vendor\bin\pint --test
vendor\bin\phpstan analyse --memory-limit=1G --no-progress
php artisan test
composer test:critical
```

## Runtime Preflight & Artifact Checks
Make sure your local MySQL and Redis are running before executing these.
```powershell
php artisan booking:doctor --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --verify-frozen --json
```

## Frontend Verification

**Staff Web**
```powershell
cd staff-web
npm run test
npm run build
cd ..
```

**Customer Web**
```powershell
cd customer-web
npm run lint
npm run typecheck
npm run test
npm run build
npm run test:e2e:smoke
cd ..
```

## Evidence Logging Examples

To capture evidence for a release, you can redirect the JSON or text output of the commands to a file. Ensure the destination directory exists first.

```powershell
mkdir -Force docs/verification-evidence
php artisan booking:doctor --json > docs/verification-evidence/doctor-output.json
php artisan booking:release-manifest --verify-frozen --json > docs/verification-evidence/release-manifest-output.json
vendor\bin\phpstan analyse --memory-limit=1G --no-progress > docs/verification-evidence/phpstan-output.txt
```
