# Staff-Web Browser Smoke Policy — Console & Resilience Specification

This document details the quality assurance, error handling, and runtime execution rules governing the `staff-web` browser-level UAT smoke automation suite.

---

## 1. Resilience & Fail-Fast Criteria

To guarantee high confidence and protect against silent visual regression (such as blank screens, failed chunk imports, or broken bundle hydration), the Playwright runner enforces the following strict rules:

### A. Blank Screen Failure
- If the root mounting element (`div#root` or `body`) remains empty or lacks key page components after navigation completes, the test must immediately fail.

### B. Page Errors
- Any unhandled exceptions (`page.on('pageerror')`) thrown inside the browser context represent critical JS runtime regressions and will cause immediate test failure.

### C. Console Errors (Non-Allowlisted)
- Any message logged via `console.error` will trigger a failure unless its text content matches a designated pattern in the allowlist.
- `console.warn` logs do not fail the test but are reported for advisory hygiene.

### D. Critical API Mismatches
- Page navigations resulting in HTTP 500 status codes on core API routes (`/api/v1/staff/...`) represent backend regressions and fail the test.
- Optional or missing capabilities that result in HTTP 403 or 404 statuses must be handled gracefully by UI error blocks or permission gates, and are allowed to warn or skip instead of crashing.

---

## 2. Console Allowlist Configuration

The allowed console anomalies are centralized in `config/staff-web-browser-smoke.allowlist.json`.

| Pattern | Reason | Expiry/Follow-up | Owner | Severity |
| :--- | :--- | :--- | :--- | :--- |
| **React Router Future Flag** | Advisory v7 router future transition logs | Batch 14 | QA Team | Warn |
| **Vite Dev Server connection** | Web socket handshakes in dev-server environment | Batch 14 | Vite Config | Warn |
| **React 18 concurrent rendering** | StrictMode double-rendering or concurrency warnings | Batch 14 | QA Team | Warn |

---

## 3. Empty vs. Failure State Resolution

To ensure automated runs do not fail simply due to a lack of live transaction records, we distinguish between UI failures and empty data states:

- **Pass (Empty State)**: The page renders correctly, displays illustrative blocks (e.g., "Không có đặt bàn nào" or "Chưa neo bàn"), and resolves loading skeletons cleanly without console/page errors.
- **Fail (Crash State)**: The page turns blank, freezes inside a loading skeleton indefinitely, or encounters JavaScript exceptions.
- **Skipped with Reason**: The test path is skipped dynamically if critical pre-conditions (such as a running local Redis or seeded DB tables) are unavailable.
