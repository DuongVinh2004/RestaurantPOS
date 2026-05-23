# Cashier Shift Close E2E Smoke Results

- **Overall Status**: PASS
- **Cashier Shift ID**: 7

| Step | Status | Detail |
|---|---|---|
| Staff Auth | PASS | Logged in staff successfully. |
| Close Existing Shift | PASS | Pre-existing shift ID 6 successfully closed. |
| POST Open Shift | PASS | Opened dedicated cashier shift ID 7 with opening float: 200000 VND. |
| POST Close Shift | PASS | Shift 7 successfully settled and closed. |
| Verify Post-Close Guard | PASS | Double closure or payment operations on a closed shift correctly rejected by business rule. |
