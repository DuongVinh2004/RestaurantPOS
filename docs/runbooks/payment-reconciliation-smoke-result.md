# Payment Reconciliation E2E Smoke Results

- **Overall Status**: PASS
- **Reservation ID**: 104
- **Session Code**: sim-dep-24407415-5b55-4f24-841d-1b0dee9f6013

| Step | Status | Detail |
|---|---|---|
| Customer Auth | PASS | Logged in customer successfully. |
| GET Available Tables | PASS | Found 12 tables. |
| POST Table Hold | PASS | Table hold bc4dccb4-676c-4321-b8fe-a0198d648cb4 created. |
| POST Reservation | PASS | Reservation 104 created. |
| POST Payment Session | PASS | Deposit session created: sim-dep-24407415-5b55-4f24-841d-1b0dee9f6013 |
| POST Webhook Callback | PASS | Simulated callback ingested and applied successfully. |
| Verify Reconciliation Status | PASS | Reservation deposit status successfully reconciled to [Paid]. |
| Verify Idempotency Guard | PASS | Double-webhook invocation correctly flagged as duplicate and did not duplicate charge. |
