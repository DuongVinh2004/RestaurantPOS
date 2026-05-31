# Payment Webhook Signature Hardening Runbook

This runbook documents the cryptographic verification, configuration, threat model, secret rotation, and operational steps for payment provider webhooks (VNPay and MoMo) in the RestaurantPOS production-grade backend.

---

## 1. Threat Model & Security Architecture

Webhooks are critical ingress points that trigger financial transitions and complete order/checkout lifecycles. Protecting these channels is paramount to prevent fraud and database inconsistency.

### Attack Vectors & Mitigations

| Attack Vector | Impact | Mitigation in RestaurantPOS |
| :--- | :--- | :--- |
| **Forged Callback** | Attacker simulates successful payment notifications to bypass paying for services/orders. | **Signature Verification**: Mandatory cryptographically signed payloads validated using SHA512 (VNPay) or SHA256 (MoMo) before entering any application workflows. |
| **Payload Tampering** | Attacker alters amount, currency, or order ID within a signed payload. | **Canonical Construction**: Signatures are checked against sorted query parameters (VNPay) or exact sequential fields (MoMo), ensuring any modified value breaks signature verification. |
| **Replay Attacks** | Attacker intercepts a valid webhook and resends it later to cause duplicate state transitions or duplicate payments. | **Database Idempotency**: Unique constraint `uq_payment_provider_webhook_receipts__provider_code__provider_event_code` and Redis lock safeguards prevent reprocessing. |
| **Signature Timing Attacks** | Attacker guesses signature bytes by measuring server comparison latency. | **Constant-time Comparison**: All adapter verification routines use constant-time `hash_equals()` to eliminate timing side-channel leakage. |
| **Misconfiguration Fallbacks** | System falls back to sandbox modes or generic stubs in production due to empty keys. | **Fail-Closed Security**: Both VNPay and MoMo adapters instantly return `false` on signature validation if operational secrets are not set or left empty. |

---

## 2. Cryptographic Verifiers

### A. VNPay Adapter (`vnpay`)
VNPay uses a sorted query-string signature verification technique based on **SHA512-HMAC**.
1. **Parameter Sorting**: Retrieve all `vnp_` parameters from the query string or raw payload. Exclude signature keys (`vnp_SecureHash` and `vnp_SecureHashType`).
2. **Alphabetical Sorting**: Keys must be sorted alphabetically via `ksort()`. Empty or null values are skipped.
3. **URL Encoding**: Convert parameters into a unified query string format using `key=value` fragments joined by `&`. Spec-compliant URL encoding (`urlencode()`) is applied to both key and value.
4. **Signature Verification**: Hash the generated query string via HMAC-SHA512 using the `booking.payment_providers.providers.vnpay.hash_secret` merchant secret. Perform a constant-time comparison against the incoming `vnp_SecureHash`.

### B. MoMo Adapter (`momo`)
MoMo utilizes a sequential canonical payload structure signed with **SHA256-HMAC**.
1. **Canonical Parameters**: Exactly sequence the following keys:
   `partnerCode`, `orderId`, `requestId`, `amount`, `orderInfo`, `orderType`, `transId`, `resultCode`, `message`, `payType`, `responseTime`, `extraData`
2. **String Sequencing**: Concatenate keys and values exactly as:
   `accessKey={accessKey}&amount={amount}&extraData={extraData}&message={message}&orderId={orderId}&orderInfo={orderInfo}&orderType={orderType}&partnerCode={partnerCode}&requestId={requestId}&responseTime={responseTime}&resultCode={resultCode}&transId={transId}&payType={payType}`
3. **Signature Verification**: Hash this canonical sequence via HMAC-SHA256 using the configured `booking.payment_providers.providers.momo.secret_key`. Perform a constant-time comparison against the incoming `signature` parameter.

---

## 3. Configuration & Deployment Setup

To activate real providers, add the following configuration environment variables to your target environments.

> [!WARNING]
> Never commit real production credentials to repository files or Git histories. Always inject them securely using environment managers or container secret mounts.

### Env Configuration Parameters

```ini
# --- VNPAY Webhook Configuration ---
PAYMENT_PROVIDER_VNPAY_ENABLED=true
PAYMENT_PROVIDER_VNPAY_MODE=live
VNPAY_TMN_CODE=your_production_tmn_code
VNPAY_HASH_SECRET=your_production_hash_secret_key
VNPAY_RETURN_URL=https://yourdomain.com/checkout/callback
VNPAY_IPN_URL=https://yourdomain.com/api/v1/payments/webhooks/vnpay

# --- MOMO Webhook Configuration ---
PAYMENT_PROVIDER_MOMO_ENABLED=true
PAYMENT_PROVIDER_MOMO_MODE=live
MOMO_PARTNER_CODE=your_production_partner_code
MOMO_ACCESS_KEY=your_production_access_key
MOMO_SECRET_KEY=your_production_secret_key
MOMO_IPN_URL=https://yourdomain.com/api/v1/payments/webhooks/momo
```

---

## 4. Test Vectors & Rehearsal Guide

Below are sample payloads to simulate correct webhook notifications locally and on staging.

### A. VNPay Simulation Test Vector

Send a POST/GET request to `/api/v1/payments/webhooks/vnpay` with the following parameters:

**Raw Payload**:
```http
vnp_Amount=5000000&vnp_Command=pay&vnp_CreateDate=20260531120000&vnp_CurrCode=VND&vnp_IpAddr=127.0.0.1&vnp_Locale=vn&vnp_OrderInfo=Deposit+payment&vnp_OrderType=other&vnp_ReturnUrl=https%3A%2F%2Fpos.test%2Freturn&vnp_TmnCode=TMN123&vnp_TxnRef=vnp-session-abc-123&vnp_Version=2.1.0&vnp_SecureHash=your_computed_sha512_hash
```

*Note: For local validation, compute the signature using your local configured `VNPAY_HASH_SECRET` key.*

### B. MoMo Simulation Test Vector

Send a POST request to `/api/v1/payments/webhooks/momo` with the following JSON body:

**JSON Body**:
```json
{
  "partnerCode": "MOMO123",
  "orderId": "momo-session-abc-123",
  "requestId": "request-456",
  "amount": "150000",
  "orderInfo": "Deposit payment for reservation",
  "orderType": "momo_wallet",
  "transId": "99887766",
  "resultCode": "0",
  "message": "Successful",
  "payType": "qr",
  "responseTime": "2026-05-31T12:00:00Z",
  "extraData": "deposit",
  "signature": "your_computed_sha256_hash"
}
```

---

## 5. Secret Rotation Procedures

In case merchant keys are compromised, follow these recovery steps immediately:

1. **Obtain New Keys**: Fetch fresh cryptographic credentials from your provider merchant portal (VNPay Partner Portal or MoMo Developer Console).
2. **Update Environment Secrets**: Update variables in your secret manager (e.g., AWS Secrets Manager, HashiCorp Vault, Kubernetes secrets) or edit the `.env` on target environments:
   - For VNPay: Update `VNPAY_HASH_SECRET` and `VNPAY_TMN_CODE`.
   - For MoMo: Update `MOMO_SECRET_KEY` and `MOMO_ACCESS_KEY`.
3. **Execute Configuration Refresh**: Run the Artisan cache refresh command to reload configuration arrays:
   ```bash
   php artisan config:cache
   ```
4. **Verify Endpoint Health**: Check outbox and general webhook logs:
   ```bash
   php artisan booking:doctor
   ```
5. **Re-run Smoke Tests**: Trigger a controlled sandbox checkout transaction to ensure signature matching remains green.
