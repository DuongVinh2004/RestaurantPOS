# Staging Provider Reconciliation Runbook — Batch 15

- **Status**: PASS (Simulated reconciliation matches exactly; manual template defined for merchant dashboard verification)

---

## 1. Manual Merchant Dashboard Audit Checklist
When verifying callbacks on live staging targets with actual VNPay/MoMo dashboard references, the QA operator must fill out the following audit details:

| Audit Parameter | Expected Staging Value | Actual Dashboard Reference | Status |
|---|---|---|---|
| **App Session ID** | E.g. `gph-7a892b10-...` | Matches `provider_session_code` | [ ] |
| **Transaction ID** | Matches `provider_payment_code` | Generated transaction ref | [ ] |
| **Amount (Gross)** | 500,000 VND | Matches billing exact amount | [ ] |
| **Currency Code** | VND | VND | [ ] |
| **Payment Scope** | `deposit` or `bill` | Matches operational purpose | [ ] |
| **Transaction Status** | `Succeeded` | `SUCCESS` / `00` | [ ] |

---

## 2. Discrepancy Reporting
- If any parameter mismatches between the application database ledger (`payment_sessions` table) and the provider dashboard:
  1. Halt execution immediately.
  2. Flag transaction status as `discrepancy_detected`.
  3. Audited errors will be logged automatically in the notification platform outbox for admin alerts.
