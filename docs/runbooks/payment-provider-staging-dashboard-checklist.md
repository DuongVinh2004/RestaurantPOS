# Payment Provider Staging Dashboard Checklist

This checklist assists the Payment Owner and Operator in provisioning the VNPay and MoMo Sandbox dashboards, setting up staging-specific callbacks, and verifying webhook integrations safely.

> [!IMPORTANT]
> - NEVER use live production merchant credentials in the staging environment.
> - NEVER commit raw callback bodies containing high-entropy signatures, passwords, or PII.
> - ALWAYS mask or redact transaction references and identifiers in evidence reports.

---

## 1. VNPay Sandbox Provisioning Checklist

### Staging Parameter Configuration
- [ ] **VNPay Merchant Terminal Code (`VNPAY_TMN_CODE`)**: Obtain a valid terminal code from the VNPay Sandbox dashboard.
- [ ] **VNPay Hash Secret (`VNPAY_HASH_SECRET`)**: Retrieve the sandbox secure hash secret key used to generate and verify signatures.
- [ ] **VNPay IPN/Callback URL (`VNPAY_IPN_URL`)**: Set this in the VNPay Merchant Portal to point to:
  `https://<your-staging-domain>/api/v1/payments/webhooks/vnpay`
- [ ] **VNPay Return URL**: Configure the return redirect endpoint in the portal:
  `https://<your-staging-domain>/reservations/new`

### Transaction & Verification Smoke Tests
- [ ] Initiate a test deposit self-payment from the frontend customer reservation flow.
- [ ] Complete the payment using the VNPay Sandbox test banking application or test credit card numbers.
- [ ] **Webhook Signature Verification**: Verify that the backend successfully resolves and verifies the callback query parameters.
- [ ] **Idempotency Check**: Replay the webhook delivery and confirm that the backend returns a successful response without creating duplicate payment entries.

### Required Staging Evidence
Compile the redacted transaction result in your staging manual evidence pack:
```json
{
  "provider": "vnpay",
  "environment": "sandbox",
  "terminal_code": "VNPAY_TMN_CODE",
  "vnp_TxnRef": "redacted_ref_123456",
  "vnp_ResponseCode": "00",
  "vnp_SecureHashType": "SHA512",
  "signature_match": true,
  "idempotency_pass": true,
  "status": "PASS"
}
```

---

## 2. MoMo Sandbox Provisioning Checklist

### Staging Parameter Configuration
- [ ] **MoMo Partner Code (`MOMO_PARTNER_CODE`)**: Obtain a sandbox partner identifier from the MoMo Partner Portal.
- [ ] **MoMo Access Key (`MOMO_ACCESS_KEY`)**: Retrieve the API access key associated with the sandbox account.
- [ ] **MoMo Secret Key (`MOMO_SECRET_KEY`)**: Retrieve the high-entropy signature key.
- [ ] **MoMo IPN/Callback URL (`MOMO_IPN_URL`)**: Set the webhook URL in the MoMo portal to:
  `https://<your-staging-domain>/api/v1/payments/webhooks/momo`
- [ ] **MoMo Return URL**: Configure the client redirect URL:
  `https://<your-staging-domain>/reservations/new`

### Transaction & Verification Smoke Tests
- [ ] Initiate a test payment from the reservation self-service dashboard.
- [ ] Authorize the transaction using the MoMo Sandbox Wallet application or test OTP credentials.
- [ ] **Signature Verification**: Confirm the incoming POST payload matches the SHA256 HMAC signature computed by the backend.
- [ ] **Reference Validation**: Verify the incoming `amount`, `orderId`, and `requestId` map directly to the active deposit session.

### Required Staging Evidence
Compile the redacted MoMo transaction result in your staging manual evidence pack:
```json
{
  "provider": "momo",
  "environment": "sandbox",
  "partner_code": "MOMO_PARTNER_CODE",
  "orderId": "redacted_order_123456",
  "requestId": "redacted_request_123456",
  "resultCode": 0,
  "signature_match": true,
  "idempotency_pass": true,
  "status": "PASS"
}
```

---

## 3. Staging Callback Evidence Posture

Mark the final verification state in the staging report:
- **PASS**: Real sandbox payment processed from the customer frontend, webhook callback received successfully, signature matched, and status updated idempotently.
- **PARTIAL**: Local adapter tests (`VNPayPaymentProviderAdapterTest` and `MoMoPaymentProviderAdapterTest`) pass, but real portal callbacks are not configured or triggered.
- **BLOCKED**: Credentials are missing from the environment, signature verification fails, or callback endpoints are unreachable (e.g., DNS/HTTPS errors).
