# Staging Cutover Rehearsal

## Goal
Validate that all infrastructure and deployment processes work on Staging exactly as they would on Production.

## Prerequisites
- Domain configured for staging (`staging.yourdomain.com`).
- S3 Backup configured and tested.
- Datadog/Sentry/Slack integrations correctly point to Staging channels.
- SSL Certificate generated.

## Steps
1. **Infrastructure Lockdown:**
   Follow `docs/runbooks/infra-provisioning.md` on the Staging VPS.
2. **Secrets:**
   Ensure `.env` variables are correctly set using Secret Manager.
3. **Database Restore:**
   Use `scripts/ops/restore-from-s3.sh` to populate the Staging DB.
4. **Deploy:**
   Run `docker-compose -f docker-compose.prod.yml up -d --build`.
5. **Preflight Checks:**
   Run `php artisan booking:deploy-check --mode=preflight --strict`.
6. **Patch Dry Run:**
   Execute `scripts/ops/db-patch-dry-run.sh path/to/patch.sql`.
7. **Smoke Testing:**
   Run Playwright using `npx playwright test --config=playwright.prod.config.ts`.
8. **Load Testing:**
   Run `k6 run load_test.js` against the staging environment.
9. **Go/No-Go Decision:**
   Confirm all checks passed. If any gate fails, halt production cutover.
