# Staging Scheduler & Runtime Heartbeat Runbook

This runbook defines the runtime operation, diagnostic checks, and maintenance guidelines for the RestaurantPOS staging scheduler heartbeat mechanism.

---

## 1. Context & Architecture

### What is the Scheduler Heartbeat?
The **Scheduler Heartbeat** is a high-integrity diagnostic mechanism that proves the Laravel scheduler is active, running continuously, and capable of executing time-critical operational logic. 

It is recorded in Redis at the key:
```text
ops:heartbeat:scheduler
```
Each execution of the scheduler touches this key, writing a UTC ISO-8601 timestamp with a standard TTL of **300 seconds** (configurable via `SCHEDULER_HEARTBEAT_TTL_SECONDS`).

### Why is it Required for Launch Readiness?
Preflight gates (`booking:deploy-check` and `booking:launch-readiness`) verify the health of background infrastructure before declaring a candidate package staging-ready or release-candidate-ready. 

A missing or stale heartbeat indicates that either:
1. The background scheduler worker has crashed or is not running.
2. The cron job is misconfigured or disabled.
3. The cache/Redis connection is failing.

If the heartbeat is stale or missing, these gates will fail-fast with high severity, blocking promotional cutover until background service integrity is restored.

---

## 2. Local Development & Testing Instructions

In a local or sandboxed execution environment where a daemon runner (like crontab or Supervisor) is not running continuously, the heartbeat will eventually expire.

### Manual Heartbeat Priming
For manual testing and pre-commit checks, operators can manually poke the heartbeat storage using the custom artisan command:
```bash
php artisan booking:ops-heartbeat:touch scheduler --json
```

### Automated Local Watch Loop
To avoid manual pokes during persistent local development, use the provided operator scripts. These scripts check for local PHP binaries and keep the heartbeat active every 60 seconds in the foreground.

- **Bash (Linux/macOS)**:
  ```bash
  ./scripts/ops/start-scheduler-heartbeat.sh --watch
  ```
- **PowerShell (Windows)**:
  ```powershell
  .\scripts\ops\start-scheduler-heartbeat.ps1 -Watch
  ```

---

## 3. Staging Infrastructure Configuration

For staging environments, the scheduler and background queues must be managed by continuous, automated system services.

### Option A: System Crontab (Recommended for Scheduler)
Laravel's scheduler runs as a cron entry. Edit the `www-data` crontab:
```bash
sudo crontab -u www-data -e
```
Append the standard scheduling entry:
```text
* * * * * cd /var/www/restaurantpos && php artisan schedule:run >> /dev/null 2>&1
```

### Option B: Supervisor Daemon (Alternative for persistent schedule work)
Alternatively, you can run the scheduler using Laravel's continuous worker command under Supervisor:

**`/etc/supervisor/conf.d/restaurantpos-scheduler.conf`**:
```ini
[program:restaurantpos-scheduler]
process_name=%(program_name)s
command=php /var/www/restaurantpos/artisan schedule:work
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/restaurantpos/storage/logs/scheduler-daemon.log
```
Apply the configuration:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start restaurantpos-scheduler
```

---

## 4. Diagnostics & Verification Commands

Operators should verify the heartbeat using standard preflight checks:

### 1. General Doctor Diagnostics
Check if the scheduler is healthy:
```bash
php artisan booking:doctor --json
```
Verify that `"runtime"."scheduler"."ok"` reports `true`.

### 2. Deploy Preflight Audit
Verify the release package and data safety constraints:
```bash
php artisan booking:deploy-check --mode=preflight --strict --json
```

### 3. Staging Launch Readiness Gate
Inspect overall launch viability:
```bash
php artisan booking:launch-readiness --target=staging --json
```

---

## 5. Troubleshooting Stale Heartbeats

If the doctor or deploy preflight reports a stale/missing heartbeat error:
1. **Verify Redis Connectivity**:
   Ensure Redis is running:
   ```bash
   redis-cli ping
   ```
2. **Inspect Scheduler Logs**:
   Read the Supervisor or laravel log output:
   ```bash
   tail -n 100 storage/logs/laravel.log
   tail -n 100 storage/logs/scheduler-daemon.log
   ```
3. **Verify Cron Status**:
   Ensure the `cron` system daemon is active:
   ```bash
   sudo systemctl status cron
   ```

---

## 6. Excluded Scope & Remaining Blockers

While this realignment resolves the primary `runtime.scheduler` blocker, staging cutover remains blocked by the following outstanding environment credentials and integrations:
- **Payment Provider Sandbox Credentials**: VNPAY/MoMo API keys and signing secrets must be populated with verified test environment credentials.
- **S3 Storage Configuration**: AWS/MinIO credentials for automated backup and restore (`backup-to-s3.sh`) are configured as placeholders and require staging bucket configurations.
- **Observability Alert Webhooks**: Slack/Discord/Sentry webhook URLs are stubs and must be pointed to actual channels.
- **Manual QA/Operator Approval**: Promotion to production-ready status requires dry-runs and sign-off on the live staging dashboard.
