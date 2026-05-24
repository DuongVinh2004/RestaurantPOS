# Batch 17 — VNPay Sandbox Dashboard Checklist

This checklist defines the operational requirements for verifying and configuring the VNPay Sandbox merchant portal to work correctly with the staging environment of RestaurantPOS.

---

## 1. VNPay Sandbox Configuration Checklist

| Task / Parameter | Required Configuration / Value | Current Status (PRESENT/MISSING) | Verification Details |
|---|---|---|---|
| **Sandbox Merchant Account Access** | Access to VNPay Sandbox Portal dashboard. | **PRESENT** | Sandbox portal access confirmed. |
| **Merchant TMN Code** | VNPay sandbox unique identifier (`VNPAY...`). | **PRESENT** | Linked in `.env` under `PAYMENT_PROVIDER_VNPAY_TMN_CODE`. |
| **Hash Secret Key** | VNPay secure hash signature key (never commit!). | **PRESENT** | Configured in secure secret storage / `.env` on host. |
| **Sandbox Pay Endpoint** | Sandbox payment page endpoint. | **PRESENT** | Set in `PAYMENT_PROVIDER_VNPAY_PAY_URL` config. |
| **IPN URL (Webhook)** | `https://<public-staging-url>/api/v1/payments/providers/generic_http_hmac/webhooks` | **MISSING** (Awaiting Public URL) | Destination for VNPay server-to-server transaction status confirmation. |
| **Return URL (Redirect)** | `https://<public-staging-url>/customer/reservations/checkout-success` | **MISSING** (Awaiting Public URL) | User-facing redirect page upon VNPay wallet/bank completion. |
| **IP Whitelist** | Check and configure staging public IP whitelist in VNPay Merchant dashboard. | **MISSING** | Whitelist sandbox IP scope if VNPay enforces inbound network blocks. |
| **Test Cards/Bank Accounts** | VNPay mock test banks list (e.g. NCB card number `9704198526153611910`). | **PRESENT** | NCB / Sandbox test bank credentials mapped in runbook. |
| **IPN Delivery Logs** | Check and confirm VNPay IPN Logs show delivered codes matching our reservation IDs. | **MISSING** (Awaiting IPN) | Check merchant transaction history search console. |

---

## 2. Security Compliance Policy

> [!WARNING]
> - Never write actual credentials or secrets (such as the hash secret key) in this or any other markdown runbook.
> - Never upload screenshot evidence that contains plaintext merchant secret keys or PII data.
> - When registering config presence, use only `PRESENT` or `MISSING` markers.
