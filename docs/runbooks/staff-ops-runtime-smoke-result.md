# Staff Operations Runtime Smoke Results

- **Overall Status**: PASS
- **Critical Failed**: 0
- **Skipped**: 0
- **Deferred**: 0

| Step | Status | Detail |
|---|---|---|
| Health/Readiness | PASS | API is healthy |
| Customer Auth | PASS | Got customer token |
| GET available tables | PASS | Got available tables |
| POST table hold | PASS | Created hold for tables: 188,189 |
| POST reservation | PASS | Reservation created: 85 |
| Customer Deposit Payment | PASS | Paid and Confirmed reservation deposit |
| Staff Auth | PASS | Got staff token |
| Staff Reservation Check-in | PASS | Successfully checked in reservation 85 |
| Active Service Session Lookup | PASS | Verified active session on table 188 matches reservation 85 |
| Cashier Shift Verification | PASS | Current open cashier shift active: 4 |
| Dine-in Order Creation | PASS | Order 28 created on table 188 with item Bánh flan caramel |
| Modify Order Items | PASS | Successfully added extra items to order 28 |
| Kitchen Dispatch | PASS | Dispatched order 28 to kitchen. Created 2 tickets. |
| Kitchen Station Tickets Lookup | PASS | Fetched kitchen tickets for station 4 |
| Kitchen Fire Ticket | PASS | Successfully fired ticket 14 |
| Kitchen Bump Ticket | PASS | Successfully bumped/completed ticket 14 |
| Checkout Preview | PASS | Fetched settlement preview. Outstanding balance: 405000 VND |
| Order Settlement & Payment | PASS | Paid 405000 VND for order 28 successfully via Cash |
| Reporting Endpoint Health | PASS | Daily sales reporting endpoint is operational and healthy |
