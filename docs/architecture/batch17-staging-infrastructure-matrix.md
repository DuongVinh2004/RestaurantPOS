# Batch 17 — Staging Infrastructure Requirement Matrix

This document provides a highly structured checklist and specifications matrix detailing the requirements to transition RestaurantPOS from local loopback smoke testing to a real, live staging environment with external payment gateway integration.

---

## 1. Staging Infrastructure Requirements

| Requirement Area | Detailed Specification | Current Local Status | How to Verify | Owner | Risk | Blocking? |
|---|---|---|---|---|---|---|
| **Public HTTPS Endpoint** | A valid TLS/SSL secured HTTPS URL (e.g. `https://staging.restaurantpos.example.com`) mapping to our backend API webhook receiver. | `http://localhost:8000` (Local loopback only) | Run `curl -i -X POST https://<your-public-url>/api/v1/payments/providers/generic_http_hmac/webhooks` without signature. Expected response: `401 Unauthorized` with signature error. | DevOps Engineer / Staging Operator | Medium: DNS resolution or routing delays. | **YES** |
| **MoMo Sandbox Callback** | Real sandbox merchant partner account callback delivery. | Simulated internally in local memory. | Verify MoMo sandbox transaction from dynamic customer flow receives a real callback, exiting with `PASS`. | Payment Engineer | High: Incorrect webhook URL on partner dashboard. | **YES** |
| **VNPay Sandbox IPN** | Real sandbox merchant TMN account return and IPN callback delivery. | Simulated internally in local memory. | Confirm redirect/return parameters match and IPN webhook updates reservation database record state correctly. | Payment Engineer | High: Secret hash key mismatch between staging and portal. | **YES** |
| **Secret Management** | Secure injection of env secrets (`DB_PASSWORD`, `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET`, etc.) without committing `.env`. | Handled via local `.env` file. | Run `npm run e2e:staging-env-audit` and verify all required keys are marked `PRESENT` with zero leaks. | DevSecOps Engineer | Medium: Exposure of merchant sandbox keys. | **YES** |
| **Scheduler / Cron Daemon** | Active cron system process continuously invoking `php artisan schedule:run` every minute. | Manual trigger via command line console helper. | Verify `php artisan booking:deploy-check --mode=preflight --strict` returns exit code `0` with a fresh scheduler heartbeat. | Staging Sysadmin | High: Preflight checks strict fail due to stale heartbeat. | **YES** |
| **Queue Workers** | Daemon service (e.g. Supervisor) monitoring queue execution `php artisan queue:work --queue=default`. | Handled synchronously (`sync` connection). | Dispatch test notifications outbox event and verify queue processes it automatically without manual execution. | Staging Sysadmin | Low: Outbox processing delay. | **YES** |
| **Database & Redis** | Production-like persistent MySQL and Redis cache database connection. | MySQL local instance + Redis local instance. | Check ping times and connection counts. Ensure SQL migrations match preflight check expectations exactly. | DBA / Sysadmin | Medium: Port exposure or credential lock. | **YES** |
| **Export Storage & Permissions** | Performant accounting CSV export storage with read/write access to storage files. | Local file system `storage/app/booking_release/`. | Run `npm run e2e:staging-export-load-smoke`. Verify files are created successfully with no permissions errors. | QA Engineer | Low: Drive space or write permissions. | **YES** |

---

## 2. Domain / Tunnel Deployment Options Analysis

Since local loopback cannot accept external payments, the staging environment must be exposed securely via one of the following methods:

### Option A: Real Staging Domain (Recommended for Long-Term UAT)
- **Description**: Assign a sub-domain (e.g., `staging-api.restaurantpos.vn`) with standard Nginx/Apache reverse proxy and Let's Encrypt TLS certificate.
- **Pros**:
  - Stable, predictable endpoint URL.
  - Easier to configure on MoMo/VNPay merchant portals once.
  - Closer to production architecture.
- **Cons**:
  - Requires public IP or cloud instance.
  - Requires DNS configuration.
- **Setup Steps**:
  1. Point DNS A-record to staging server public IP.
  2. Run `certbot` to generate a free SSL certificate.
  3. Map Nginx `proxy_pass` to backend service port (e.g., `http://127.0.0.1:8000`).
- **Security**: Strict firewall rules allowing only HTTP/HTTPS traffic (ports 80/443).

### Option B: Ngrok Tunnel (Recommended for Fast Verification)
- **Description**: Lightweight secure tunnel mapping port 8000 to a temporary public HTTPS domain.
- **Pros**:
  - Simple, zero DNS configuration.
  - Works instantly from local development environments.
- **Cons**:
  - Free tier changes URL on every restart (requires constantly updating MoMo/VNPay portal callbacks).
  - Subject to ngrok rate limits.
- **Setup Steps**:
  1. Install ngrok agent.
  2. Run command: `ngrok http 8000`.
  3. Copy generated HTTPS URL to env files and provider portal settings.
- **Security**: Do not expose sensitive `/admin` endpoints publicly without strict password protection or API keys.

### Option C: Cloudflare Tunnel (Argo Tunnel)
- **Description**: Secure, fast connection between local backend and Cloudflare network without opening firewall ports.
- **Pros**:
  - Extremely secure; no public port forwarding required.
  - Permanent free URL mapped directly to Cloudflare DNS.
- **Cons**:
  - Requires domain registered on Cloudflare DNS.
  - Requires `cloudflared` daemon daemonized on server.
- **Setup Steps**:
  1. Authenticate `cloudflared login`.
  2. Create tunnel: `cloudflared tunnel create staging-tunnel`.
  3. Map tunnel routing to DNS: `cloudflared tunnel route dns staging-tunnel staging-api.restaurantpos.vn`.
  4. Start tunnel mapping: `cloudflared tunnel run --url http://localhost:8000 staging-tunnel`.
- **Security**: Cloudflare Access policies can be attached to gate access.
