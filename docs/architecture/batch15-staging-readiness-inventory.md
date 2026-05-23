# Staging Readiness Inventory — Batch 15

This inventory maps all required architectural infrastructure, environment configurations, and external dependency requirements needed to safely run RestaurantPOS on a production-like staging target.

---

## 1. Environment & Infrastructure Registry

| Required Service | Required Config Keys | Current Local Support | Staging Target Target | Action Needed | Risk |
|---|---|---|---|---|---|
| **MySQL Database** | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | SQLite / Local MySQL | MySQL 8 compatible cluster | Verify connectivity and run preflight deploy-check | Low |
| **Redis Cache / Lock** | `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB` | phpredis / Predis | Active Redis Server | Verify set/get keys and lock execution | Medium |
| **Queue Worker** | `QUEUE_CONNECTION` | `sync` | `database` or `redis` managed via Supervisor | Run active queue worker daemon | Medium |
| **Scheduler / Cron** | `SCHEDULER_HEARTBEAT_TTL_SECONDS` | Local script | Crontab ticking `php artisan schedule:run` every minute | Verify continuous heartbeat | High |
| **Staff Authentication** | `STAFF_AUTH_DATABASE_STORE_ENABLED` | Mock `bootstrap-admin` | Database-backed credentials and staff keys | Verify pre-seeded cashiers | Low |
| **Customer JWT Auth** | `CUSTOMER_AUTH_JWT_SECRET` | Static key | High-entropy secret managed via env | Verify customer token signing | Low |
| **Export File Storage** | `FILESYSTEM_DISK` | Local disk | Storage folder with write permission | Verify accounting/reconciliation writes | Medium |

---

## 2. Payment Integration Config Boundaries

| Config Variable | Role & Purpose | Required Sandbox Setting | Secret Policy |
|---|---|---|---|
| `PAYMENT_CUSTOMER_SELF_PAY_ENABLED` | Master gate enabling self-checkout bill pay | `true` | Public boolean |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_ENABLED` | Enables the generic HTTP HMAC driver | `true` | Public boolean |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MODE` | Set adapter environment mode | `sandbox` | Public string |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BASE_URL`| Outbound mock/sandbox API endpoint base URL | E.g. `https://api.sandbox.momo.vn` or mock base | Public URL |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_SECRET` | Signing key for outgoing checkout sessions | High-entropy sandbox HMAC secret key | **Strictly Secret** (No env commit) |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET` | Secret key to verify incoming callbacks | High-entropy sandbox HMAC webhook key | **Strictly Secret** (No env commit) |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SIGNATURE_HEADER` | Ingestion header for signature audits | `X-Payment-Signature` | Public string |
| `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_TIMESTAMP_HEADER` | Ingestion header for clock drift audits | `X-Payment-Timestamp` | Public string |

---

## 3. Webhook callback Routing Parity

1. **MoMo Webhook Callback Endpoint**:
   - Route path: `POST /api/v1/customer/self-service/payments/webhook` (Ingests generic HTTP HMAC signatures)
   - Staging SSL/HTTPS: Required. Payment providers block sandbox notification delivery to non-HTTPS endpoints.
   - Merchant Code: Dynamic config bound to `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MERCHANT_CODE`.
2. **VNPay Webhook Redirect Endpoint**:
   - Route path: `GET /api/v1/customer/self-service/payments/confirm` (Validates return query parameters using hash checks)
   - Staging return URL: `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/confirm`
