# Payment Reconciliation E2E Smoke Results

- **Overall Status**: PASS
- **Reservation ID**: 88
- **Session Code**: sim-dep-e3de182c-18ea-4069-88b8-d4db87552c2d

| Step | Status | Detail |
|---|---|---|
| Customer Auth | PASS | Logged in customer successfully. |
| GET Available Tables | PASS | Found 8 tables. |
| POST Table Hold | PASS | Table hold e940543e-0ab5-4077-bf79-d2d20a89d8d8 created. |
| POST Reservation | PASS | Reservation 88 created. |
| POST Payment Session | PASS | Deposit session created: sim-dep-e3de182c-18ea-4069-88b8-d4db87552c2d |
| POST Webhook Callback | PASS | Simulated callback ingested and applied successfully. |
| Verify Reconciliation Status | PASS | Reservation deposit status successfully reconciled to [Paid]. |
| Verify Idempotency Guard | PASS | Double-webhook invocation correctly flagged as duplicate and did not duplicate charge. |
