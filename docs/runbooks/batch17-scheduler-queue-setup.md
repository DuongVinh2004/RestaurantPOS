# Batch 17 — Scheduler & Queue Background Worker Setup Runbook

To maintain the high-integrity operational state of the RestaurantPOS staging backend, background schedule processing (scheduler) and background jobs execution (queue worker) must be handled by continuous, system-level daemon processes rather than manual operator CLI interventions.

This document details the configuration options for staging environments.

---

## 1. Scheduler Daemon Setup (Cron)

Laravel requires a cron daemon to execute scheduling rules defined inside the application kernel.

### Linux Crontab Option
Log in as the web application user (e.g., `www-data` or the staging service account) and run:
```bash
crontab -e
```

Append the following single-line entry:
```text
* * * * * cd /var/www/restaurantpos && php artisan schedule:run >> /dev/null 2>&1
```

*This triggers the schedule manager every 60 seconds to process reporting snapshots, data lifecycle anonymization, and outbox notification heartbeats automatically.*

---

## 2. Queue Worker Daemon Setup (Supervisor)

For high-volume transaction processing and outbox notifications, queue workers should run continuously. Using Supervisor is the industry-standard way to manage these processes on Linux hosts.

### Sample Supervisor Configuration File
Create a new configuration block in `/etc/supervisor/conf.d/restaurantpos-worker.conf`:

```ini
[program:restaurantpos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/restaurantpos/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/restaurantpos/storage/logs/worker.log
stopwaitsecs=3600
```

### Apply Configuration
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start restaurantpos-worker:*
```

---

## 3. Docker Compose Setup Option (Containerized Infrastructure)

If the staging environment runs inside a multi-container Docker cluster, map the scheduler and queue workers as decoupled, long-running services inside your `docker-compose.yml`:

```yaml
version: '3.8'
services:
  # Main Web App Service
  app:
    image: restaurantpos-staging:latest
    # ...

  # Staging Cron Scheduler Service
  scheduler:
    image: restaurantpos-staging:latest
    restart: always
    command: sh -c "while [ true ]; do php artisan schedule:run; sleep 60; done"
    depends_on:
      - app

  # Staging Queue Worker Service
  queue-worker:
    image: restaurantpos-staging:latest
    restart: always
    command: php artisan queue:work --sleep=3 --tries=3
    depends_on:
      - app
```

---

## 4. Verification Check Runbook

### Step 1: Let the Heartbeat Accumulate
Do NOT execute manual touch commands (e.g. `php artisan booking:ops-heartbeat:touch`). Let the newly installed scheduler cron or docker daemon run for at least **2 minutes** to verify the automatic heartbeat resolves.

### Step 2: Execute Diagnostics & Deploy Checks
Verify that the system registers as completely healthy. Run the following strict audits:

```bash
# 1. Check strict preflight check (Must PASS automatically)
php artisan booking:deploy-check --mode=preflight --strict --json

# 2. Check general platform health audit
php artisan booking:doctor --json

# 3. Check migration package integrity
php artisan booking:release-manifest --json
```

### Step 3: Audit Outbox & Snapshot Projections
If the application features outbox metrics or reporting snapshots commands, check their statuses:
```bash
# Check reporting status
php artisan booking:reporting-snapshots:status --json
```

---

## 5. Pass/Fail Evaluation Criteria

- **PASS**:
  - The strict preflight check (`booking:deploy-check`) returns exit code `0` with a verdict of `PASS`.
  - The scheduler heartbeat age is confirmed under the TTL limit (e.g. $< 60$ seconds) without manual touch commands.
- **FAIL**:
  - Succeeded only after manual console heartbeat touches (`booking:ops-heartbeat:touch scheduler`) or manual reporting snapshots rebuilds. Staging daemon verification is invalid until the cron is properly configured.
