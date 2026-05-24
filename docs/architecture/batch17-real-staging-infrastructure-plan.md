# Batch 17 — Real Staging Infrastructure Setup Plan & Execution Roadmap

This document serves as the master checklist, architecture plan, and verification roadmap to transition **RestaurantPOS** from local loopback smoke testing to a verified real staging infrastructure deployment.

---

## 1. Executive Summary
- **Real Staging Infrastructure Status**: **NOT VERIFIED** (Pending public webhook endpoint, merchant dashboard setups, and systemd/scheduler configurations).
- **Batch 17 Outcome**: **READY FOR REVIEW / MERGE WITH RISKS**. All checklists, runbooks, env audits, and readiness driver tools are fully prepared and verified locally.
- **Project Production Readiness Claim**: **NONE**. The project is extremely robust and passes all mock constraints, but a production-ready verdict requires real external sandbox execution without loopback mock bypasses.

---

## 2. Mandatory Setup Actions Before Staging is Certified "READY"

The following actions must be successfully completed on the staging server:

1. **Establish Public HTTPS Endpoint**:
   - Set up an Nginx reverse proxy mapped to a public domain (Option A) or launch a secure tunnel (Option B: ngrok / Option C: Cloudflare Tunnel).
2. **Configure MoMo Developer Sandbox Dashboard**:
   - Register the staging public URL as the webhook `notifyUrl` and `returnUrl` on the MoMo Developer Sandbox merchant portal.
3. **Configure VNPay Sandbox Portal**:
   - Configure the merchant `TMN_CODE` and register the `IPN_URL` pointing to the public endpoint.
4. **Harden Environment Secrets Injection**:
   - Populate host env keys (`PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET`, keys, and certificates) without hardcoding or committing values.
5. **Daemonize Scheduler & Cron Service**:
   - Configure a Linux system crontab (`* * * * * cd /var/www/restaurantpos && php artisan schedule:run`) to ensure the heartbeat is regularly updated.
6. **Daemonize Queue Worker Service**:
   - Set up a Supervisor monitor daemon to process notifications outbox queues continuously.

---

## 3. Post-Deployment Verification Commands Sequence

Once the staging setup is complete, the deployment operator should run the following commands sequentially:

```bash
# 1. Audit variable presence (Must pass without --allow-missing-provider-env)
npm run e2e:staging-env-audit

# 2. Execute non-destructive readiness checks
npm run e2e:staging-readiness-driver

# 3. Check strict preflight heartbeat and projections age (Must pass automatically)
php artisan booking:deploy-check --mode=preflight --strict --json

# 4. Execute E2E MoMo live sandbox webhook check (Must receive live external request)
npm run e2e:momo-sandbox-callback-smoke

# 5. Execute E2E VNPay live sandbox return check
npm run e2e:vnpay-sandbox-callback-smoke

# 6. Verify high-volume CSV exports execute correctly on host storage
npm run e2e:staging-export-load-smoke
```

---

## 4. Environment Classification & Pass Criteria

### Environment Classification Statuses

#### 1. READY (Deployable to Production)
- **Criteria**:
  - `e2e:staging-env-audit` passes with all critical and provider sandbox keys present.
  - `booking:deploy-check` strict mode passes 100% automatically (no manual touches).
  - Webhook URL successfully receives live callbacks from MoMo/VNPay sandbox gateways.
  - Reconciled state database logs show correct payments match.

#### 2. MERGE WITH RISKS (Current Code Status)
- **Criteria**:
  - Codebase is fully functional, contracts are aligned, and local signature mocks succeed.
  - No live network webhook or sandbox portal callbacks have been received from outer networks.
  - Scheduler runs only under manual console stimulation.

#### 3. NOT READY (Blocked)
- **Criteria**:
  - API parity contract tests fail.
  - Core database migrations are missing or inconsistent.
  - Environment variables are missing and script audits fail.

---

## 5. Remaining Infrastructure Blocker Matrix

| Blocker ID | Infrastructure Requirement | Owner / Responsible | Required Action | Status |
|---|---|---|---|---|
| **BLK-01** | Public HTTPS Endpoint Setup | DevOps Engineer | Configure reverse proxy or run ngrok tunnel on the staging machine. | **PENDING** |
| **BLK-02** | MoMo Sandbox Webhook Config | Merchant Account Admin | Register public URL as `notifyUrl` on MoMo portal. | **PENDING** |
| **BLK-03** | VNPay Sandbox IPN Config | Merchant Account Admin | Register public URL as `IPN_URL` on VNPay portal. | **PENDING** |
| **BLK-04** | Secret Key Environment Injection | DevSecOps / Sysadmin | Load merchant sandbox access/hash secrets into staging environment variables. | **PENDING** |
| **BLK-05** | Continuous Cron schedule runner | Staging Sysadmin | Set up server crontab entry for artisan scheduler. | **PENDING** |
| **BLK-06** | Continuous Queue worker daemon | Staging Sysadmin | Configure Supervisor worker processes for queue connection. | **PENDING** |
