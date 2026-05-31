# Runbook — Notification Channel Integration

This runbook outlines the operational requirements, environment settings, and safety mechanisms for the RestaurantPOS Notification Platform.

---

## 1. Scope
This runbook covers the configuration and deployment boundaries of the notifications subsystem (Email, SMS, and Zalo). It details how to keep local/test environments safely isolated from real delivery, while allowing verified rehearsals in staging and strict enforcement of real delivery targets in production.

---

## 2. Supported Channels
RestaurantPOS defines a multi-channel outbox architecture:
- **Email:** The primary communication channel, using a `production_lean` readiness posture. Integrates directly with Laravel's mailer configurations.
- **SMS:** A stub-based channel using `SmsStubNotificationChannelDriver` with a `provider_ready` posture (restricted to stubs and simulation).
- **Zalo:** A stub-based channel using `ZaloStubNotificationChannelDriver` with a `provider_ready` posture (restricted to stubs and simulation).
- **Stub/Log Channel for Local/Test:** Default local development uses `log` or `array` drivers so no real external alerts are triggered.

---

## 3. Environment Variables
Configure the following keys in your `.env` or staging secrets manager:

```ini
# Notification Platform configuration
NOTIFICATIONS_OUTBOX_ENABLED=true
NOTIFICATIONS_OUTBOX_MAILER=smtp   # Set to 'smtp' for real delivery, 'log' for dry run
NOTIFICATIONS_OUTBOX_BATCH_SIZE=20
NOTIFICATIONS_OUTBOX_LOCK_SECONDS=90
NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS=5

# Staging Smoke Rehearsal Recipient
NOTIFICATION_SMOKE_EMAIL=smoke@example.test

# Channel enablement
NOTIFICATIONS_EMAIL_ENABLED=true
NOTIFICATIONS_EMAIL_DRIVER=mail
NOTIFICATIONS_SMS_ENABLED=false
NOTIFICATIONS_SMS_DRIVER=stub
NOTIFICATIONS_ZALO_ENABLED=false
NOTIFICATIONS_ZALO_DRIVER=stub
```

---

## 4. Local/Test Behavior
- `NOTIFICATIONS_OUTBOX_MAILER` must defaults to `log` or `array`.
- No real SMTP connection is required.
- SMS and Zalo drivers should remain `stub`.
- Preflight validation warnings are treated as informational local gaps.

---

## 5. Staging Rehearsal
To prove that email delivery works in staging without spamming real customers:
1. Populate `NOTIFICATION_SMOKE_EMAIL` in the environment with a verified staging testing address.
2. Run the safe delivery rehearsal Artisan command:
   ```bash
   php artisan notifications:delivery-smoke
   ```
3. This command will:
   - Enqueue a dummy `reservation.created` smoke notification.
   - Run the outbox processor locally.
   - Verify the message status transitions to `Sent`.
   - Write a safe, redacted evidence JSON to `storage/app/booking_release/manual_evidence/notification-delivery-*.json`.

---

## 6. Production Requirements
- **Blocked Log Mailer:** Setting `NOTIFICATIONS_OUTBOX_MAILER=log` is BLOCKED in production-like environments and will fail launch-readiness preflight checks.
- **Durable Infrastructure:** Production must use a live transactional SMTP server.
- **No Stub Drivers:** Any active customer-facing stub drivers (SMS/Zalo) are blocked.

---

## 7. Safe Smoke Recipient Policy
- Never use real customer email addresses or phone numbers for testing.
- Only enqueues to explicit `NOTIFICATION_SMOKE_EMAIL` targets are permitted.
- The delivery smoke command automatically blocks if targeted at production targets without the `--force` flag.

---

## 8. Outbox Worker Requirements
Background workers must execute the outbox runner regularly (e.g. via crontab or supervisor):
```bash
* * * * * php artisan notifications:process-outbox --limit=20
```

---

## 9. Retry/Failure Behavior
- If a delivery attempt fails due to a retryable error (e.g. connection timeout), it backoffs using the following intervals: `[1, 5, 15, 60]` minutes.
- If it fails due to a non-retryable error (e.g. invalid email format) or exceeds 5 attempts, it is automatically marked as `Cancelled` (Dead-lettered).
- The oldest pending age is monitored and will trigger warnings if a message remains unstuck for more than 15 minutes (`900` seconds).

---

## 10. Evidence JSON Format
Rehearsal drills generate a PII-redacted JSON payload at `storage/app/booking_release/manual_evidence/`:

```json
{
  "rehearsal_type": "notification_delivery_rehearsal",
  "channel": "Email",
  "recipient_masked": "smo***@example.test",
  "environment": "staging",
  "outbox_id": 142,
  "status": "Sent",
  "attempt_count": 1,
  "processed_at_utc": "2026-05-31T03:00:00Z",
  "mailer": "smtp",
  "success": true
}
```

---

## 11. Common Failures
- **SQLSTATE[HY000] General error: 1 no such table:** The test or temporary SQLite database was not migrated before running the process command. Solution: touch the migration baseline or run `php artisan migrate`.
- **Mailer "log" is not permitted:** The application resolved as staging/production target, but mail settings fell back to log. Solution: Configure real credentials.

---

## 12. Verification Commands
Run these diagnostic commands to verify health:
```bash
# General outbox breakdown and counts
php artisan notifications:outbox-health --json

# Inspect dead letters
php artisan notifications:outbox-dead-letter --limit=10 --json
```

---

## 13. Remaining Blockers
- Real SMTP credentials must be securely injected in staging/production environments (using AWS Secrets Manager or secure `.env`).
- Outbox workers must be daemonized on staging instances.

---

## 14. No Production-Ready Claim
This integration framework enables robust dry-run testing and safe staging rehearsals, but **does not** imply the live system is certified for full production cutover until manual mail delivery evidence has been successfully completed in a live staging rehearsal.
