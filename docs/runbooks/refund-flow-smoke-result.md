# Refund Flow E2E Smoke Results

- **Overall Status**: PASS
- **Reservation ID**: 104

| Step | Status | Detail |
|---|---|---|
| Load Test Precondition | PASS | Successfully loaded reservation ID 104 from prior smoke result. |
| Staff Auth | PASS | Logged in staff successfully. |
| GET Reservation Detail | PASS | Current row_version is 4, status is [Confirmed]. |
| GET Refund Preview | PASS | Verified refundable amount is: 500000.00 VND. |
| POST Reservation Refund & Cancel | PASS | Successfully processed Card refund and cancelled reservation. |
| Verify Duplicate Refund Guard | PASS | Duplicate refund request correctly caught and prevented from double refund. |
