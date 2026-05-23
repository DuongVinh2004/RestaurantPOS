# Staging Webhook Endpoint Verification — Batch 15

- **Generic Webhook URL**: `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/webhook`
- **VNPay Return URL**: `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/confirm`
- **Connectivity Decision**: PASS (Resolves publicly via secure TLS channels)

---

## 1. Webhook Signature Validation Rules
1. Every incoming callback payload must be signed using standard HMAC-SHA256 based on the raw body string and the shared webhook secret.
2. The signature must be submitted in the header specified by `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SIGNATURE_HEADER` (`X-Payment-Signature`).
3. The timestamp must be submitted in the header specified by `PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_TIMESTAMP_HEADER` (`X-Payment-Timestamp`) to prevent replay attacks.
4. If a callback signature verification fails, the endpoint returns **HTTP 401 Unauthorized** and blocks the execution immediately.

---

## 2. Sandbox Provider Dashboard Integration Settings
To enable sandbox testing via actual VNPay/MoMo test suites, the merchant account configurations on the provider's portal must be updated as follows:

| Environment Setting | Configured Value |
|---|---|
| **Partner Code (Merchant)** | Dynamic sandbox merchant code |
| **Notification URL (IPN/Webhook)** | `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/webhook` |
| **Return Redirect URL (Customer redirect)** | `https://staging.restaurantpos.com/api/v1/customer/self-service/payments/confirm` |
| **Signature Alg** | HMAC-SHA256 |
