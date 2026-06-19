# Staging Credential Readiness Handoff

## 1. Purpose
This runbook serves as the final operational checklist before launching the RestaurantPOS backend into the Staging environment. It provides the necessary steps to safely inject real credentials and pass the required launch readiness gates.

## 2. Why `launch-readiness` correctly fails without real credentials
The `php artisan booking:launch-readiness --target=staging` command is a strict preflight gate designed to prevent incomplete or unsafe deployments. It intentionally fails in local or unconfigured environments because it asserts the presence and validity of actual 3rd-party SaaS integrations (AWS, Sentry, Mail, VNPay, MoMo). Bypassing this gate using placeholders is strictly prohibited to ensure production parity.

## 3. Required credentials
The following environment variables MUST be provided on the Staging server:

- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET` (S3 bucket/config)
- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `SENTRY_LARAVEL_DSN`
- `SLACK_WEBHOOK_URL` (nếu dùng)
- `MOMO_PARTNER_CODE`
- `MOMO_ACCESS_KEY`
- `MOMO_SECRET_KEY`
- `VNPAY_TMN_CODE`
- `VNPAY_HASH_SECRET`

## 4. Where to configure secrets
Secrets should be configured in the deployment environment's `.env` file or securely injected via the CI/CD pipeline secrets manager (e.g., GitHub Secrets, AWS Secrets Manager, or Doppler). Do NOT commit these to the repository.

## 5. How to verify without printing secrets
To safely verify that credentials have been loaded without exposing them in CI/CD logs:
- Run `php artisan config:cache` to ensure Laravel parses the `.env`.
- Check health status safely via `php artisan booking:doctor --json`.

## 6. Commands to run after secrets are configured
Execute the following sequence on the Staging environment:
```bash
composer bootstrap:booking
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:launch-readiness --target=staging --json
```

## 7. Expected pass criteria
All commands must exit with code `0`. The `launch-readiness` JSON output must show `"decision": "pass"` without any blocking missing credentials.

## 8. Rollback plan
If `launch-readiness` fails or staging behaves unexpectedly:
1. Revert to the last known stable deployment branch/tag.
2. Ensure database integrity by running `composer bootstrap:booking` which applies idempotent SQL-first patches safely.
3. Turn off Day-2 feature flags in `.env` if logic errors occur.

## 9. Feature flag rollout order
Once staging deployment passes the automated gates, manually enable feature flags in this strict order, requiring UAT sign-off for each step:
1. `customer.bill_self_payment`
2. `inventory.uplift`
3. `staff.kitchen_dispatch`
4. `waiting_list.advanced_automation`
5. `staff.conversation_inbox`
6. `staff.conversation_ai_assist`
