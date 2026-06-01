# Staging Observability & Alerting Checklist

This checklist guides the Operator through configuring Sentry error tracking, wiring Slack/Ops webhooks, and verifying alerting pipeline health.

> [!IMPORTANT]
> - NEVER commit actual Sentry DSN URLs or Slack webhook web addresses.
> - ALWAYS verify that `SENTRY_ENVIRONMENT` is set explicitly to `staging` in the staging environment.
> - ALWAYS redact sensitive fields (like slack workspace names or token fragments) in the evidence pack.

---

## 1. Credentials Configuration
Populate the following variables securely via the server env or secret manager:
- [ ] **Sentry DSN (`SENTRY_LARAVEL_DSN`)**: The Sentry client connection URL. Sentry Laravel SDK uses this to automatically capture unhandled exceptions.
- [ ] **Sentry Environment (`SENTRY_ENVIRONMENT`)**: Set explicitly to `staging`.
- [ ] **Ops Webhook (`OPS_ALERTS_WEBHOOK_URL`)**: The high-priority Slack/Discord or Ops Alerting channel webhook URL used for critical system warning dispatches.

---

## 2. Observability Smoke Test Execution
To trigger a mock alert verification and check that the alerting pipelines route messages successfully, execute:

### Bash (Linux/MacOS/Git Bash)
```bash
# Verify alerting health in staging mode
bash scripts/ops/check-alerting-health.sh --staging
```

### PowerShell (Windows)
```powershell
# Verify alert-check through Artisan
php artisan booking:alert-check --fail-on-alert
```

Verify that:
- [ ] The command completes successfully without network or connection errors.
- [ ] If Sentry is enabled, a test issue or event is dispatched and registered on the Sentry Dashboard.
- [ ] A mock warning alert reaches the configured Slack/Ops alerting channel.

---

## 3. Expected Evidence & Safety Posture

Review the generated evidence output:
- **PASS**: The test alert executes successfully, a Slack notification is delivered to the channel, a Sentry event is captured on the dashboard, and a redacted receipt is logged.
- **PARTIAL**: The alert evaluator (`booking:alert-check --dry-run`) executes successfully with 0 failures, but real webhooks or DSN configurations are omitted (simulation only).
- **BLOCKED**: One of the following errors occurs:
  - **DSN Missing**: Sentry Laravel integration is disabled or DSN empty.
  - **Webhook Missing**: `OPS_ALERTS_WEBHOOK_URL` is empty.
  - **403/404 Forbidden**: Webhook URL is invalid or expired.
  - **Connection Timeout**: Network policies restrict outbound webhook requests.
  - **Event Not Visible**: Webhook returns a successful status, but the event never appears in the target Slack channel or Sentry Dashboard.
