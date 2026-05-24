# Staging Refund & Reversal Check — Batch 15

- **Status**: PASS (Double-refund guards fully validated; provider-direct APIs marked as staging environment blocked)

---

## 1. Provider Sandbox Refund Whitelist Limitation
- **MoMo / VNPay Sandbox Direct Refund**: Staging environment is blocked from calling the direct sandbox refund APIs. These APIs require explicit **merchant server IP whitelisting** on the provider's administrator console, which cannot be granted on local/dynamic staging server endpoints.
- **UAT Mitigation Plan**: Sandbox refund pipelines must be tested using simulated callback parameters under `generic_http_hmac` rollout setups, or by exercising the safe staff UAT `/refund-cancel` endpoint.

---

## 2. Dynamic UAT Double-Refund Guard
- Every cashier or customer-initiated refund posted to `/reservations/{id}/refund` or `/refund-cancel` requires checking previous ledger lines in the `payment_sessions` table.
- Subsequent refund attempts on an already fully refunded transaction are strictly rejected with a `domain_invariant_violation` response to prevent double-charging or merchant balance depletion.
