# Payment Provider Inventory

This document registers and catalogs all active and supported payment provider integrations inside the **RestaurantPOS** backend architecture.

## Registered Providers

| Provider Name | Adapter Class | Config Key Prefix | Default Status | Scope / Role |
|---|---|---|---|---|
| **Simulated** | `SimulatedPaymentProviderAdapter` | `booking.payment_providers.providers.simulated` | `Enabled` in Local | Sandbox simulation of all payment states. |
| **Generic HTTP HMAC** | `GenericHttpHmacPaymentProviderAdapter` | `booking.payment_providers.providers.generic_http_hmac` | `Disabled` (Staging-Only) | Adaptable wrapper for standard webhooks (MoMo, VNPay, PayOS, Stripe, etc.) |

---

## Detailed Integration Mapping

### 1. Simulated Provider
- **Config Keys Required**:
  - `PAYMENT_PROVIDER_SIMULATED_ENABLED` (bool)
  - `PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET` (string)
  - `PAYMENT_PROVIDER_SIMULATED_ENFORCE_SIGNATURE` (bool)
- **Webhook Endpoint**: `POST /api/v1/payments/providers/simulated/webhooks`
- **Signature Algorithm**: `HMAC-SHA256` matching `X-Payment-Signature` header (stripped of `sha256=` prefix).
- **Payment Session Create**: `/reservations/{id}/deposit/payment-sessions` (customer/deposit) or standard cashier pos bill checkout.
- **Confirm/Reconcile Endpoint**: `POST /api/v1/reservations/{id}/deposit/payment-sessions/{session_id}/confirm`
- **Refund Endpoint**: `POST /api/v1/staff/reservations/{id}/refund`
- **Current Test Coverage**: Covered under `staff-ops-runtime-smoke.mjs` (simulated payment confirm path).
- **Local Support**: **Full**. Works locally without external internet connections.
- **Staging Support**: **Full**. Used as fallback/UAT verification adapter.
- **Risk**: **Low**.
- **Action Needed**: Verify webhook-driven confirmation flow under signature checking.

### 2. Generic HTTP HMAC Provider (MoMo / VNPay Staging Parity)
- **Config Keys Required**:
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_ENABLED` (bool)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BASE_URL` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MERCHANT_ID` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MERCHANT_CODE` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_API_KEY` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_API_SECRET` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SIGNING_SECRET` (string)
  - `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET` (string)
- **Webhook Endpoint**: `POST /api/v1/payments/providers/generic_http_hmac/webhooks`
- **Signature Algorithm**: Configurable HMAC (`sha256`, `sha1`, `md5`) validating the request raw body against configured headers:
  - Signature Header: `X-Payment-Signature` (configurable via `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SIGNATURE_HEADER`)
  - Timestamp Header: `X-Payment-Timestamp` (configurable via `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_TIMESTAMP_HEADER`)
- **Payment Session Create Endpoint**: `POST /sessions` (outgoing request to the provider base URL).
- **Confirm/Reconcile Endpoint**: `POST /sessions/{session_code}/confirm` (outgoing check to the provider).
- **Refund Endpoint**: Outgoing refund requests are routed to specific vendor endpoints when supported.
- **Current Test Coverage**: Evaluated by unit tests in `GenericHttpHmacPaymentProviderAdapterTest.php`.
- **Local Support**: **Staging-Blocked**. Requires real webhook callbacks from sandboxes unless mock harnesses are active.
- **Staging Support**: **Full**. Enabled in sandbox environments using merchant keys for MoMo/VNPay.
- **Risk**: **High**. Webhook signatures fail if timestamps drift or HMAC keys mismatched.
- **Action Needed**: Set up test harnesses checking valid, invalid, and expired signatures without relying on real credentials.

---

## Observability & Audit
Webhooks verify unique delivery via `Idempotency-Key` or custom receipt fingerprints. Every transaction outputs audit trail records to the `audit` log channel under categories `applied`, `ignored` (terminal state regression), or `failed` (signature or processing issues) protecting downstream cash ledgers from replay double-charges.
