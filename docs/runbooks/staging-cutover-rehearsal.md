# Staging Cutover Rehearsal Checklist

> [!IMPORTANT]
> If you are provisioning a real Ubuntu VPS for staging cutover, please refer directly to the **[Live Staging Operator Deployment Pack](file:///c:/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/runbooks/live-staging-operator-deployment-pack.md)**. It provides step-by-step, copy-pasteable configuration for Nginx, Supervisor, MySQL, Redis, and more.

This runbook defines the exact sequence required to provision, deploy, and verify the RestaurantPOS application in a staging environment. It must be executed fully before declaring staging as "production-like" or promoting the build to production.

---

## 1. Chuẩn bị server (Server Preparation)

### Software Requirements
- **PHP**: >= 8.2 (with extensions: bcmath, ctype, fileinfo, json, mbstring, openssl, pdo, tokenizer, xml)
- **Composer**: >= 2.x
- **Node.js**: >= 18.x (for frontend builds)
- **MySQL**: 8.0+
- **Redis**: 7.0+
- **Supervisor**: For process management (Queue / Scheduler workers)
- **Nginx/Caddy**: For reverse proxy and SSL termination

---

## 2. `.env.staging` Configuration

Ensure the staging environment variables are strictly defined. **DO NOT** use placeholder values (e.g., `changeme`, `secret`, `test`) for any of the following production-like secrets:
- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_KEY` (must be a valid base64 key)
- `CUSTOMER_AUTH_JWT_SECRET` (must be high-entropy, >= 32 chars)
- `SENTRY_LARAVEL_DSN` (must be an active DSN)
- `LOG_SLACK_WEBHOOK_URL` (must be an active webhook)
- `OPS_ALERTS_WEBHOOK_URL` (must be an active webhook)
- `DB_CONNECTION=mysql` (SQLite is forbidden in staging)
- `REDIS_HOST`, `REDIS_PASSWORD` (Redis is required for locking and queues)

*Note: The doctor/deploy checks will fail-fast if dummy secrets are detected.*

---

## 3. Worker Processes (Queue & Scheduler)

In staging and production, you must use Supervisor or systemd to daemonize the Laravel queue and scheduler.

### Queue Worker
Configure Supervisor to run the queue worker continuously:
```ini
[program:restaurantpos-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/restaurantpos/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
```

### Scheduler
Run the Laravel scheduler worker to keep the `ops:heartbeat:scheduler` alive and process cron tasks:
```ini
[program:restaurantpos-scheduler]
process_name=%(program_name)s
command=php /var/www/restaurantpos/artisan schedule:work
user=www-data
autostart=true
autorestart=true
```
Apply configs: `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all`

---

## 4. Bootstrap SQL-first

Never use `php artisan migrate` for initial setup. Use the canonical SQL-first bootstrap:
```bash
composer bootstrap:booking
```
If you are applying a patch to an existing database, run:
```bash
mysql -u [user] -p [database] < database/patches/[patch_file].sql
```

---

## 5. Build Frontend

Build both the staff and customer frontends to ensure static assets and API clients are compiled:
```bash
# Build Staff Web
cd staff-web
npm install
npm run build

# Build Customer Web
cd ../customer-web
npm install
npm run build
```

---

## 6. API Contract Generation/Check

Verify that API artifacts (OpenAPI, Postman, TypeScript enums) match the current backend schema.
```bash
php artisan booking:release-manifest --verify-frozen --json
```

---

## 7. Diagnostics & Health Checks

Run the doctor command to verify DB, Redis, and Scheduler heartbeat.
```bash
php artisan booking:doctor --strict --json
```
*Expected: The `runtime.scheduler.ok` should be true. If not, verify the Scheduler worker is running.*

---

## 8. Deploy Check

Run the strict preflight deployment check to ensure data invariants and schemas are intact.
```bash
php artisan booking:deploy-check --mode=preflight --strict --json
```

---

## 9. Launch Readiness

Validate feature flags and final launch readiness.
```bash
php artisan booking:launch-readiness --target=staging --json
```
*(Day-1 flags: `customer.bill_self_payment`, `staff.kitchen_dispatch` etc. are expected to be off for staff-only operations.)*

---

## 10. Smoke Test

Run the backend test suite and verification commands:
- `php artisan test`
- `php artisan notifications:outbox-health`
- Run frontend E2E tests (if configured): `npx playwright test --config=playwright.prod.config.ts`

---

## 11. Rollback

If any step fails, abort the cutover immediately:
1. Revert to the last known-good release package.
2. Re-point the symlink.
3. Clear caches: `php artisan optimize:clear`.
4. Restore DB from the latest S3 backup (using `scripts/ops/restore-from-s3.sh`) if a bad SQL patch was applied.

---

## 12. Log Locations

- **Application Logs**: `storage/logs/laravel.log`
- **Supervisor Logs**: `/var/log/supervisor/`
- **Doctor/Deploy Reports**: `storage/app/booking_release/doctor/reports/`, `storage/app/booking_release/deploy_checks/reports/`

---

## 13. Common Failure Modes

- **Missing Heartbeat**: `ops.scheduler_heartbeat` fails during `booking:deploy-check`.
  - *Fix*: Start `php artisan schedule:work` via Supervisor.
- **Dummy Secrets Blocked**: `booking:doctor` fails with `Dummy, seed, or placeholder credentials...`.
  - *Fix*: Update `.env.staging` with real webhook URLs and DSNs, not `changeme`.
- **CORS Failure**: Frontend cannot hit the API.
  - *Fix*: Verify `CORS_ALLOWED_ORIGINS` precisely matches the frontend URLs.
