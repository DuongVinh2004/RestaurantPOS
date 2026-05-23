# Staff-Web Financial Browser UAT Smoke Results

- **Overall Status**: PASS
- **Test Command**: `npm --prefix staff-web run e2e:smoke`
- **Execution Date**: 24/05/2026

## Playwright Walkthrough Steps

| Step | Status | Detail |
|---|---|---|
| Step 1: Authenticate via login form | PASS | Logged in using `bootstrap-admin` cashier actor. |
| Step 1b: Cashier Shift self-healing | PASS | Dynamically detected closed shift, auto-opened a fresh cashier shift in-browser to proceed. |
| Step 2: Dashboard Page | PASS | Verified `.staff-dashboard-page` and operational stats render cleanly. |
| Step 3: Reservation Inbox Page | PASS | Verified front-of-house reservation list renders correctly. |
| Step 4: Reservation Detail Drawer | PASS | Verified timeline and reservation drawer states load safely. |
| Step 5: Table Board Layout Page | PASS | Renders the interactive table map layout grid. |
| Step 6: POS Order Workspace | PASS | POS menu selections and service session ordering load safely. |
| Step 7: Kitchen Station Board | PASS | Selects kitchen dispatch station and displays KDS tickets board. |
| Step 8: Checkout Settlement Page | PASS | Renders checkout billing preview, tax summary, and receipt view. |
| Step 9: Cashier Shift Page | PASS | Renders cash drawer float ledger, total cash records, and shifts history. |
| Step 10: Finance Review Page | PASS | Renders the settlement reconciliation overview. |
| Step 11: Admin settings & reporting | PASS | Renders Chi nhánh và thiết lập config catalog + Hub báo cáo vận hành. |
| Step 12: Session Purge Resilience | PASS | Purging auth stores triggers clean state rejection redirecting back to `/login`. |

## Key Verification Details
- **Self-Healing Shift Initialization**: Automated the step to open a cashier shift whenever a run commences in a clean state, assuring complete operational capability on subsequent pages.
- **Console Warnings Audit**: Screen features tested had zero fatal console errors or JavaScript crashes. Minor, safe UI library deprecation logs were caught under allowlist filters.
