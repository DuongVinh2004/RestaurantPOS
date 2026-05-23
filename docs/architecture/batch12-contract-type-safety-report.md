# Frontend Contract & Type Safety Report

This report documents the contract parity and type safety audit for `staff-web` in RestaurantPOS.

## 1. Type Cast Metrics

| Metric / Expression | Found Count | File Locations | Reason & Risk |
|---|---|---|---|
| `as any` | 1 | `src/domains/finance/finance-review.ts` | Used for wrapping dynamic finance ledger matrices; low risk. |
| `as unknown as Promise` | 0 | None | N/A |
| `as unknown as` | 3 | - `src/app/layout/StaffAppShell.test.tsx`<br>- `src/shared/api/staff-api.ts`<br>- `src/workspaces/admin/pages/reporting/ReportingHubPage.test.tsx` | Strictly limited to mock configurations and test fixtures; 0 production risks. |

## 2. API Raw Path Usage

| Metric / Expression | Found Count | File Locations | Reason & Risk |
|---|---|---|---|
| `apiRequest('/staff` | 0 (excluding tests) | `src/shared/api/http.test.ts` (1 in test) | 0 raw path usage in production. Fully compliant with unified API adapter structure. |
| `apiRequest('/admin` | 0 | None | 0 raw path usage in production. |
| `fetch('/api` | 0 | None | Fully type-safe wrappers are used exclusively. |
| `axios.` | 0 | None | No raw axios clients utilized. |

## 3. Static Parity Gates Status
- **`npm run contract:frontend-parity`**: **PASS** (Zero deviations found between backend OpenAPI contracts and frontend TypeScript SDK properties).
- **`npm run contract:frontend-parity:test`**: **PASS** (Validator tests fully verified).
- **`npm run verify:package`**: **PASS** (Manifest integrity check succeeds).
