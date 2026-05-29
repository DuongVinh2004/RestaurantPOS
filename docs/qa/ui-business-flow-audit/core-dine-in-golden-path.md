# Core Dine-in Golden Path Business Flow Audit

## 1. Environment
- **Customer Web**: Next.js (Localhost:3000)
- **Staff Web**: React/Vite (Localhost:5173)
- **Backend**: Laravel 11 / MySQL 8 / Redis
- **Audit Branch**: `audit-core-dine-in-golden-path`

## 2. Commands used
- `npm run dev:all:reset` (to start clean environment)
- `php artisan booking:doctor --json` (Health check)
- `php artisan notifications:outbox-health --json` (Outbox check)
- Playwright script: `npx playwright test --project="chromium" e2e/golden-path-audit.spec.ts`

## 3. Test credentials/source
- Customer: Guest checkout / dynamically filled fields
- Staff: `uat.admin@example.com` / `UatDemo!123` (from `scenario-pack.json`)

## 4. URLs
- Customer Web: http://127.0.0.1:3000
- Staff Web: http://127.0.0.1:5173
- API: http://127.0.0.1:8000/api/v1

## 5. Flow timeline
See Evidence folder. The run was aborted at Step 3 due to a UI blocker.

## 6. Generated entity IDs
- customer_id: N/A (Blocked)
- reservation_id: N/A (Blocked)
- table_id: N/A
- service_session_id: N/A
- order_id: N/A
- order_item_ids: N/A
- kitchen_ticket_ids: N/A
- payment_ids: N/A

## 7. Step-by-step result table

| Step | UI | Action | Expected | Actual | Status | Evidence |
|------|----|--------|----------|--------|--------|----------|
| 1. Customer Web Access | Customer | Load homepage | Homepage loads without fatal error | Homepage loads | PASS | `01-homepage.png` |
| 2. Create Reservation | Customer | Fill booking form | Form accepts input | Form loads but branch selection dropdown locators and labels are untestable/missing | BLOCKED | `02-booking-form.png` |
| 3. Submit Reservation | Customer | Click complete | Success screen | Submit button unresponsive/disabled. Playwright script hangs trying to click | BLOCKED | N/A |
| 4. Staff Login | Staff | Login with uat.admin | Dashboard loads | Flow interrupted | BLOCKED | N/A |
| 5. Cashier Shift | Staff | Open shift | Shift opened | Flow interrupted | BLOCKED | N/A |
| 6. Find Reservation | Staff | Search by ID | Reservation listed | Flow interrupted | BLOCKED | N/A |
| 7. Assign Table | Staff | Click Assign | Table assigned | Flow interrupted | BLOCKED | N/A |
| 8. Check-in | Staff | Click Check-in | Reservation checked in | Flow interrupted | BLOCKED | N/A |
| 9. Order & KDS | Staff | Open Order | Order page loads | Flow interrupted | BLOCKED | N/A |
| 10. Checkout | Staff | Pay and finalize | Settlement complete | Flow interrupted | BLOCKED | N/A |

## 8. Bugs found
**BUG-UI-004**: Customer Web Reservation Form Blocked (High)
- Form inputs for branch selection cannot be interacted with standard roles (e.g. `option`). 
- Form submission hangs because the button remains disabled or unclickable when automated inputs are provided.
- Next.js development server encountered Turbopack compilation crashes (ENOENT errors) before functioning properly, requiring manual `.next` cache deletion.

## 9. Remaining risks
- The Staff-web flow (steps 4-10) could not be verified in this live E2E session due to the customer-web reservation flow failing.
- The `customer-web` Next.js development server (`dev:web`) is highly unstable with Turbopack on Windows (`npm run dev:all`), requiring a fallback to standard Webpack or stable cache invalidation.

## 10. Next recommended audit batch
- Review and fix `customer-web` reservation form components (Branch selection, label translation mismatches).
- Re-run Golden Path Audit once the Customer Web reservation submission is unblocked.
