# Batch 17 — MoMo Sandbox Dashboard Checklist

This checklist defines the operational requirements for verifying and configuring the MoMo Sandbox merchant portal to work correctly with the staging environment of RestaurantPOS.

---

## 1. MoMo Sandbox Configuration Checklist

| Task / Parameter | Required Configuration / Value | Current Status (PRESENT/MISSING) | Verification Details |
|---|---|---|---|
| **Sandbox Partner Portal Access** | Registered account on MoMo Developer Sandbox Portal (`https://developers.momo.vn`). | **PRESENT** | Account confirmed. |
| **Partner Code (PartnerCode)** | MoMo sandbox identifier (`MOMO...`). | **PRESENT** | Linked in `.env` under `PAYMENT_PROVIDER_MOMO_PARTNER_CODE`. |
| **Access Key** | Public integration key mapping to staging environment client. | **PRESENT** | Linked in `.env` under `PAYMENT_PROVIDER_MOMO_ACCESS_KEY`. |
| **Secret Key** | Staging HMAC signature secret key (never commit!). | **PRESENT** | Configured in secure secret storage / `.env` on host. |
| **Sandbox API Endpoint** | `https://test-payment.momo.vn/v2/gateway/api/create` | **PRESENT** | Set in `PAYMENT_PROVIDER_MOMO_API_URL` config. |
| **Notify URL (Webhook)** | `https://<public-staging-url>/api/v1/payments/providers/generic_http_hmac/webhooks` | **MISSING** (Awaiting Public URL) | Destination for high-entropy signature callback from MoMo servers. |
| **Return URL (Redirect)** | `https://<public-staging-url>/customer/reservations/checkout-success` | **MISSING** (Awaiting Public URL) | User-facing landing target upon successful payment flow completion. |
| **IP Whitelist** | Whitelist target staging public IP if restricted by MoMo security guidelines. | **MISSING** | Check if MoMo portal dashboard enforces IP block lists. |
| **Test Payment Method** | MoMo sandbox wallet app downloaded or test phone/OTP credentials. | **PRESENT** | Sandbox test user phone numbers and mock OTP credentials logged. |
| **Callback Log Verification** | Confirm MoMo portal log outputs list webhook packets delivered with HTTP status 200. | **MISSING** (Awaiting Webhook) | Check dashboard logs after a sandbox payment transaction. |

---

## 2. Security Compliance Policy

> [!WARNING]
> - Never write actual credentials or secrets (such as the secret key) in this or any other markdown runbook.
> - Never upload screenshot evidence that contains plaintext merchant secret keys or PII data.
> - When registering config presence, use only `PRESENT` or `MISSING` markers.
