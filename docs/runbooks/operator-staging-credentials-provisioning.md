# Operator Staging Credentials Provisioning Runbook

This runbook guides the Operator through the secure provisioning of staging credentials and provides a checklist for running live evidence verification on the staging-like or real staging environment.

> [!WARNING]
> NEVER commit actual secrets, `.env` files, private keys, or API tokens to the repository.
> NEVER paste secrets in pull requests, commit messages, or chat platforms.
> ALL manual evidence collected must have sensitive fields (like tokens, passwords, and PII) masked or redacted.

---

## 1. Credential Inventory Matrix

The following table summarizes all credentials, tokens, and configurations required for the staging environment.

| Area | Required Env / Secret | Owner | Where to Configure | Validation Command | Evidence Produced | Default Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **DB** | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Operator | Server env / Secret Manager | `php artisan booking:doctor` | Database runtime status | pending |
| **Redis** | `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Operator | Server env / Secret Manager | `php artisan booking:doctor` | Redis runtime health | pending |
| **S3 DR** | `BACKUP_S3_BUCKET`, `BACKUP_S3_PREFIX`, `AWS_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (or IAM Role) | Operator | IAM role / Secret Manager | `bash scripts/ops/run-dr-rehearsal.sh --staging` | S3 Upload & Restore drills | pending |
| **SMTP** | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_ENCRYPTION` | Operator | Secret Manager | `php artisan notifications:delivery-smoke --target=staging` | Safe smoke mail outbox logs | pending |
| **Sentry** | `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT` | Operator | Secret Manager | `php artisan booking:alert-check --target=staging` | Alerting health dashboard | pending |
| **Slack Webhook** | `OPS_ALERTS_WEBHOOK_URL` | Operator | Secret Manager | `bash scripts/ops/check-alerting-health.sh --staging` | Real slack notification receipt | pending |
| **VNPay sandbox** | `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_IPN_URL` | Payment Owner | Provider Dashboard & Secret Manager | `php artisan test --filter=VNPayPaymentProviderAdapterTest` | Sandbox API callback logs | pending |
| **MoMo sandbox** | `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `MOMO_IPN_URL` | Payment Owner | Provider Dashboard & Secret Manager | `php artisan test --filter=MoMoPaymentProviderAdapterTest` | Sandbox API callback logs | pending |
| **Smoke Email** | `NOTIFICATION_SMOKE_EMAIL` | Operator | Secret Manager | `php artisan notifications:delivery-smoke --recipient=<email>` | safe-email outbox delivery | pending |
| **Staging URL** | `STAGING_BASE_URL` (or `APP_URL`) | Operator | Server Env | `npm run build` & browser smoke | Frontend runtime smoke logs | pending |

---

## 2. Secret Provisioning Rules

To preserve production safety, secrets must be handled according to strict operational governance guidelines:

1. **No Local Commits**: Never commit `.env` or configurations containing real secrets to the repository. The `.gitignore` is configured to prevent this; do not override it.
2. **Secrets Distribution**:
   - **For GitHub Actions**: Use GitHub Environments (`staging`, `production`) to declare environment secrets. Actions will securely inject these variables into the runners.
   - **For VPS / Linux Servers**: Inject credentials using systemd `EnvironmentFile` configurations (e.g., `/etc/restaurantpos/staging.env`), secure Docker secrets, or AWS Secrets Manager / HashiCorp Vault.
3. **AWS IAM Best Practices**:
   - **Prefer Roles**: On EC2, ECS, or EKS, associate an IAM Instance Profile / Role with the runtime compute resources to grant S3 permissions without long-lived keys.
   - **Least Privilege Access**: If access keys are mandatory, associate them with a highly restrictive IAM user using a scoped policy that limits operations to a single bucket and prefix.
4. **Credential Lifecycle**:
   - Establish a **rotation policy** (e.g., rotate SMTP and payment keys every 90 days).
   - Document a **revocation procedure** to instantly disable credentials (e.g., revoking the IAM User, changing the SMTP password) in case of a suspected breach.
5. **Masking & Auditing**:
   - Always run the credentials sanity checker (`bash scripts/ops/check-staging-credentials.sh`) before starting deployment to confirm that no production secrets or plaintext values are leaked in terminal outputs or logs.

---

## 3. Full Live Evidence Rerun Procedure

Once all staging credentials are fully provisioned, the Operator must execute the following sequence of commands to gather live evidence on the real staging environment and rebuild the readiness package.

### Step 3.1: Pre-Execution Credential Sanity Check
Ensure all environment variables are correctly populated and no placeholders remain:
```bash
# Verify credentials are set securely without leakage
bash scripts/ops/check-staging-credentials.sh --json --strict
```

### Step 3.2: Baseline Staging Runtime Gates
Verify the core application runtime is completely healthy:
```bash
# Verify base runtime and MySQL/Redis dependencies
php artisan booking:doctor --json

# Verify notification outbox has no stuck records
php artisan notifications:outbox-health --json

# Run strict preflight release checks
php artisan booking:deploy-check --mode=preflight --strict --json

# Inspect and verify release patches manifest
php artisan booking:release-manifest --json
```

### Step 3.3: Scoped Live Evidence Rehearsals
Trigger the individual operational rehearsals to generate the live evidence files:
```bash
# 1. AWS/S3 DR Restore Rehearsal
bash scripts/ops/run-dr-rehearsal.sh --staging

# 2. SMTP Outbound Mail Rehearsal
php artisan notifications:delivery-smoke --target=staging

# 3. Sentry & Slack Alert Webhook Rehearsal
bash scripts/ops/check-alerting-health.sh --staging

# 4. Payment Integrations Regression
php artisan test --filter=VNPayPaymentProviderAdapterTest
php artisan test --filter=MoMoPaymentProviderAdapterTest
php artisan test --filter=PaymentProviderWebhookVerificationTest
php artisan test --filter=PaymentProviderWebhookFlowTest
```

### Step 3.4: Rebuild Consolidated Staging Evidence Pack
Compile all individual manual evidence JSON files into a secure, single-pack manifest:
```bash
# Compile and sanitize the staging evidence pack
bash scripts/ops/build-staging-evidence-pack.sh --staging
```

### Step 3.5: Final Launch Readiness Gate Evaluation
Run the final launch readiness matrix using the freshly compiled evidence pack:
```bash
# Evaluate final readiness status
php artisan booking:launch-readiness --target=staging --manual-evidence=storage/app/booking_release/manual_evidence/manual_evidence.json --json
```

### Step 3.6: Staging Frontend E2E Smoke (Optional)
If Playwright is configured and a staging frontend URL is active:
```bash
cd customer-web
npx playwright test --config=playwright.prod.config.ts --project=chromium
cd ..
```

---

## 4. Operator Go/No-Go Sign-off

After reviewing the outputs of the Launch Readiness Gate:
1. **Analyze Blocker States**: If S3 uploads fail, SMTP connections timeout, Sentry events are delayed, or payment signatures fail, mark the specific group as **BLOCKED** and resolve the backend setup. Do NOT fake pass or bypass safeguards.
2. **Execute Sign-off**: Fill in the operator approval template at `docs/runbooks/templates/operator-staging-go-no-go.template.json` and save the resolved sign-off in the manual evidence directory:
   `storage/app/booking_release/manual_evidence/operator_approval.json`
3. **Production Cutover Guard**:
   - `production_cutover_approved` must strictly remain `false` during the staging review.
   - Any actual production cutover requires a dedicated, separate go-live review session.
