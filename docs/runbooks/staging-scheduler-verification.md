# Staging Scheduler & Cron Long-Running Verification — Batch 15

- **Scheduler Continuous Status**: PASS (Heartbeat age: 14s, threshold: 180s)
- **Queue System**: active (`database` queue driver monitored via Supervisor daemon processes)

---

## 1. Supervisor Queue Worker Configuration
Staging target utilizes robust system daemons to run continuous queue processes:
```ini
[program:restaurantpos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
```

---

## 2. Continuous Crontab Execution
To keep analytics snapshots, KDS backlogs, and database outbox notifications cleanly synchronized, the platform runs a standard crontab ticking once every minute:
```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. Reporting Snapshot Autopilot
- Configured lookback parameters: 7 days.
- Rebuild interval: every 2 hours autonomously (via `booking:reporting-snapshots:status`).
- Verified that active and completed cash bills sync automatically into the daily sales read model tables without requiring manual operator intervention.
