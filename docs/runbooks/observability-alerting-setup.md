# Runbook — Observability & Alerting Setup

This runbook outlines the operational guidelines, monitoring pipelines, and incident response playbooks for RestaurantPOS.

---

## 1. Scope
This runbook covers the integration of exception tracking (Sentry), realtime notification outbox health monitoring, deploy safety alerting, and centralized Slack operational channels.

---

## 2. Sentry Setup
To track runtime crashes and 500 spikes:
1. **Installation:** The package `sentry/sentry-laravel` is optional for local development but must be present in staging/production environment composer trees:
   ```bash
   composer require sentry/sentry-laravel
   ```
2. **Harden class checks:** To prevent developers from crashing if they do not have the package installed locally, `config/logging.php` gates the `sentry` driver via `class_exists`:
   ```php
   'sentry' => [
       'driver' => class_exists(\Sentry\Laravel\ServiceProvider::class) ? 'sentry' : 'null',
       'level' => env('LOG_LEVEL', 'error'),
   ]
   ```
3. **Environment variables:**
   ```ini
   SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project
   SENTRY_ENVIRONMENT=staging  # staging|production
   SENTRY_TRACES_SAMPLE_RATE=0.0  # 0.0 to disable performance tracing in lean environments
   ```

---

## 3. Alert Webhook Setup
Centrally capture critical signals using incoming webhooks:
1. Obtain an incoming webhook URL from your Slack/Telegram workspace.
2. In the target environment secrets manager, configure:
   ```ini
    LOG_SLACK_WEBHOOK_URL=https://slack.example.com/hooks/your-webhook-token
   ```
3. Realtime critical logs and outbox alert failures route directly to this sink.

---

## 4. Outbox Health Alert
Run the outbox health cronjob to scan backlog status:
```bash
* * * * * /bin/bash /var/www/html/scripts/ops/check-outbox-health.sh
```
This script evaluates the following thresholds:
- Degraded status if pending count exceeds `100`.
- Degraded status if failed count exceeds `10`.
- Degraded status if stale processing rows exist.
- Triggers alert notifications detailing exact metrics.

---

## 5. Runtime Health Alert
- The `booking:doctor` command evaluates live MySQL connection, Redis cache capacity, and scheduler heartbeat.
- Stale heartbeats or connection failures will trigger critical Slack alerts via cron/monitoring integrations.

---

## 6. Deploy Gate Alert
- Preflight checks run via `booking:deploy-check --mode=preflight`.
- Failure in preflight checks exits non-zero and stops the deployment flow, sending full diagnostic reports (with redacted passwords) to `LOG_SLACK_WEBHOOK_URL`.

---

## 7. Secret/PII Redaction Policy
Operational logs, exceptions, and webhooks must never expose customer or system credentials:
- **Alert Payloads:** Slack alert curl wrappers automatically redact trace fields using sed patterns:
  `sed -E 's/password=[^& ]+/password=REDACTED/g'`
- **Masked customer details:** Outbox audit logs and manual evidence JSON automatically mask customer email addresses:
  `smo***@example.test` instead of `smoke@example.test`.

---

## 8. Local/Staging/Production Behavior
- **Local:** No external alert hooks or Sentry integrations required. Degrades cleanly without crashes.
- **Staging:** Sentry and Slack alert webhooks are highly recommended. Missing keys will raise validation warnings at the preflight readiness gate.
- **Production:** SMTP, Sentry, and Alert Webhooks are **blocking** preflight requirements. Missing keys will fail the preflight readiness gate.

---

## 9. Manual Evidence
In staging, operators can verify alerting connectivity manually:
1. Execute the alert rehearsal script:
   ```bash
   bash scripts/ops/check-alerting-health.sh
   ```
2. Verify a dry-run confirmation message appears in the Slack operational channel.
3. Record the pass state under manual evidence templates.

---

## 10. Verification Commands
```bash
# Verify Sentry and Alerting gates locally
php artisan test --filter=BookingEnvironmentValidatorTest

# Test Slack alert connectivity
bash scripts/ops/check-alerting-health.sh
```

---

## 11. Incident Response Checklist
When a critical outbox health alert is received:
1. **Check Outbox Worker Status:**
   Verify if the scheduler or process queue is running:
   `ps aux | grep artisan`
2. **Review Dead Letters:**
   Inspect failed rows to determine the root cause of message blocks:
   `php artisan notifications:outbox-dead-letter --limit=20 --json`
3. **Verify Mail Server Connectivity:**
   Confirm SMTP server port `2525` or `587` is reachable:
   `telnet smtp.mailtrap.io 2525`
4. **Inspect Sentry Issues:**
   Check Sentry for any new code exception traces matching the `NotificationDeliveryException` signature.

---

## 12. No Production-Ready Claim
This monitoring and exception tracking framework establishes the necessary operational baseline for staging, but **does not** certify the live setup for direct production traffic until live Sentry monitoring has been verified on staging.
