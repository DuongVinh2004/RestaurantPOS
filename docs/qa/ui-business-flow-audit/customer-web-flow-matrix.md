# Customer Web Flow Matrix

| Flow | Steps | Expected Result | Actual Result | Status | Evidence Link | Severity |
|---|---|---|---|---|---|---|
| Menu/Catalog Browse | View live menu | Items load via API | Fail: Playwright test timeouts expecting English copy ("Browse the menu before your visit.") | Fail | Test Log | Medium |
| Auth/Login | Login | Redirects to reservations with mocked customer session | Pass in mock-backed test (`customer-smoke.spec.ts`) | Pass | Test Log | None |
| Reservation Hold | Create a hold and continue | Hold is created | Pass in mock-backed test (`customer-smoke.spec.ts`) | Pass | Test Log | None |
| Reservation Preorder | Preserve preorder draft, attach to reservation | Preorder draft attached | Pass in mock-backed test (`customer-smoke.spec.ts`) | Pass | Test Log | None |
| Deposit Payment | Simulated local UAT deposit | Deposit successful | TBD | Blocked | N/A | N/A |
| Bill Payment | Simulated bill payment | Bill successful | TBD | Blocked | N/A | N/A |
| Waiting List | Join waiting list | Success | TBD | Blocked | N/A | N/A |
| Account Benefits | View vouchers/loyalty | Displays benefits | TBD | Blocked | N/A | N/A |
| Privacy/Data Export | View/export | Works | TBD | Blocked | N/A | N/A |
