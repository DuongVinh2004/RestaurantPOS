# Customer Web Flow Matrix

| Flow | Steps | Expected Result | Actual Result | Status | Evidence Link | Severity |
|---|---|---|---|---|---|---|
| Menu/Catalog Browse | View live menu | Items load via API | Pass: Homepage loads properly and Playwright assertions fixed in Batch 1. | Pass | core-dine-in-golden-path.md | None |
| Auth/Login | Login | Redirects to reservations with mocked customer session | Pass in mock-backed test (`customer-smoke.spec.ts`) | Pass | Test Log | None |
| Reservation Creation | Create reservation from form | Form accepts input and submits | Pass: Handled the 2-step booking flow and `BottomSheet` properly with `data-testid` in Batch 2. | Pass | core-dine-in-golden-path.md | None |
| Reservation Hold | Create a hold and continue | Hold is created | Pass in mock-backed test (`customer-smoke.spec.ts`) | Pass | Test Log | None |
| Reservation Preorder | Preorder cart, preview, replace, submit draft, and lock on submit | Preorder submitted and conflict handles cleanly | Pass: Cart adding, quantity editing, notes, preview, replace, submit locking, and 422 Row version conflict friendly messaging are fully verified. | Pass | Playwright Spec | None |
| QR Bill Preview | Access table via QR code preview token | Shows order lines, totals, tax, and discount | Pass: Valid tokens correctly calculate subtotal, tax, discount, and total; invalid tokens gracefully fail-closed with friendly errors. | Pass | Playwright Spec | None |
| Deposit Payment | UAT self-service deposit preview | Deposit preview loaded | Pass: UI loads deposit summary; live payment session is out of scope. | PASS | Playwright Spec | None |
| Bill Payment | UAT self-service bill preview | Bill preview loaded | Pass: UI loads bill totals and table status; live payment session is out of scope. | PASS | Playwright Spec | None |
| Waiting List | UAT join waiting list | Success | Pass: Waitlist actions and cancel flows are covered. | PASS | Playwright Spec | None |
| Account Benefits | View vouchers/loyalty | Displays benefits | Needs Data: Account benefits rollout-gated by default on checkout / UAT environment. | NEEDS_DATA | Playwright Spec | None |
| Privacy/Data Export | View/export | Works | Pass: Anonymization and export logic verified passing at the API contract level. | PASS | Playwright Spec | None |
