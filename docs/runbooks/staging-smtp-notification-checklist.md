# Staging SMTP & Notification Delivery Checklist

This checklist guides the Operator through configuring a real SMTP relay for staging, executing safe smoke notification delivery tests, and verifying outbox health.

> [!IMPORTANT]
> - NEVER send test notifications to real customers.
> - ALWAYS verify that the `MAIL_MAILER` is configured to `smtp` (or a real provider) and NOT `log` in the staging environment.
> - ALWAYS mask or redact recipient email addresses in committed logs or evidence pack files.

---

## 1. SMTP Credential Configuration
Ensure the following variables are securely populated via the server env or secret manager:
- [ ] **Mailer Driver (`MAIL_MAILER`)**: Set explicitly to `smtp`.
- [ ] **Mail Server Host (`MAIL_HOST`)**: SMTP relay server domain (e.g., Mailgun, SendGrid, Amazon SES, or Mailtrap).
- [ ] **SMTP Port (`MAIL_PORT`)**: Typically `587` (TLS) or `465` (SSL).
- [ ] **SMTP Credentials (`MAIL_USERNAME` / `MAIL_PASSWORD`)**: Scoped mail relay user and password.
- [ ] **Encryption (`MAIL_ENCRYPTION`)**: Set to `tls` or `ssl`.
- [ ] **Safe Smoke Recipient (`NOTIFICATION_SMOKE_EMAIL`)**: A controlled, operator-owned mailbox used to verify outbound emails safely.
- [ ] **From Address (`MAIL_FROM_ADDRESS`)**: Scoped outbound sender email aligned with your SPF/DKIM policies.

---

## 2. Notification Smoke Test Execution
To verify outbound SMTP mail delivery and outbox pipeline health, run:

```bash
# Execute the safe staging notification rehearsal
php artisan notifications:delivery-smoke --recipient=<NOTIFICATION_SMOKE_EMAIL>
```

Upon execution, the command will:
1. Enqueue a safe smoke notification into the outbox database.
2. Trigger the notification dispatcher to send the enqueued row immediately.
3. Record the SMTP server response and compile an evidence file at:
   `storage/app/booking_release/manual_evidence/notification-delivery-<timestamp>.json`

---

## 3. Outbox Health Verification
Check the outbox database to ensure all enqueued notifications have been successfully processed:

```bash
# Verify outbox database health
php artisan notifications:outbox-health --json
```

Verify that:
- [ ] `pending_count` is `0`.
- [ ] `failed_count` is `0`.
- [ ] `stale_processing_count` is `0`.
- [ ] The enqueued smoke message is listed under `sent_count`.

---

## 4. Evidence Posture Evaluation

Review the generated evidence file and check if it matches one of the following states:

- **PASS**: The smoke test enqueues, dispatch succeeds, SMTP server accepts the message, and health check reports `0` pending.
- **PARTIAL**: The outbox flows successfully, but `MAIL_MAILER` is still configured to `log`, meaning no real network request reached an external SMTP host.
- **BLOCKED**: One of the following errors occurs:
  - **Auth Failed**: SMTP server rejected username/password.
  - **Connection Timeout**: Network or port configuration blocks outgoing packets.
  - **TLS/SSL Issue**: Certificate handshake failure between host and SMTP server.
  - **Stuck Outbox**: Message enqueued but outbox process fails or hangs indefinitely.
  - **Smoke Recipient Missing**: `NOTIFICATION_SMOKE_EMAIL` is not set or invalid.
