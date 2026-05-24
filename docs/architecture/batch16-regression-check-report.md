# Batch 16 — Static / Build / Package Regression Report

This document reports the package integrity, frontend contract parity, and structural build compliance for **Batch 16 - Staging Infrastructure Execution**.

## Regression Audit Results

### 1. Frontend Contract Parity
- **Command Run**:
  ```bash
  npm run contract:frontend-parity
  ```
- **Outcomes**:
  - Scanning routes found: **247** routes and **247** operationIds in frozen OpenAPI specification maps.
  - Scanning staff-web: **PASS**
  - Scanning customer-web: **PASS**
  - **Verdict**: **Parity check is fully correct**. No drift detected between PHP backend router and React/Next.js UI consuming SDKs.

### 2. Parity Scanner Negative Tests
- **Command Run**:
  ```bash
  npm run contract:frontend-parity:test
  ```
- **Outcomes**:
  - Checked validation failures under fake allowlist paths successfully.
  - **Verdict**: **PASS** (negative regression gates function as intended).

### 3. Package Integrity Compliance
- **Command Run**:
  ```bash
  npm run verify:package
  ```
- **Outcomes**:
  - Core HTTP artisan directories: **OK**
  - Database schema & SQL bootstrap tools: **OK**
  - TypeScript SDK, enums, mutation contracts generation: **OK**
  - Staged file secrets check: **OK** (no secrets staged, no `.env` files tracked)
  - **Total Items Verified**: **52**
  - **Decisions / Blocking Failures**: **PASS (0 missing, 0 stale, 0 failures)**

---

## Conclusion
All static, build, and structural integrity regression checks have successfully **PASSED**. No regressions or token/secret leaks are present.
