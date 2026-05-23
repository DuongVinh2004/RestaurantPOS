# Batch 11 — Deferred Flow & Allowlist Inventory

This document details all allowed and deferred items from `config/frontend-contract-parity.allowlist.json`, evaluating their status across the contract-to-runtime continuum.

## Allowed/Deferred API Path Inventory

| Allowlist Path Pattern | Method | Frontend App | Backend Route? | OpenAPI? | SDK Method? | Smoke Covered? | Risk | Action |
|---|---|---|---|---|---|---|---|---|
| `/staff/reservations/[\w\-]+/preorder(/.*)?` | ANY | staff-web | Yes | Yes | No (Curated out) | No | High | Add to SDK curated groups, migrate staff-web usage, add to runtime smoke, remove from allowlist. |
| `/staff/reservations/[\w\-]+/voucher(/.*)?` | ANY | staff-web | Yes | Yes | No (Curated out) | No | High | Add to SDK curated groups, migrate staff-web usage, remove from allowlist. |
| `/staff/reservations/[\w\-]+/vouchers` | ANY | staff-web | Yes | Yes | No (Curated out) | No | High | Add to SDK curated groups, migrate staff-web usage, remove from allowlist. |
| `/staff/users/[\w\-]+/loyalty(/.*)?` | ANY | staff-web | Yes | Yes | No (Curated out) | No | High | Add to SDK curated groups, migrate staff-web usage, remove from allowlist. |
| `/staff/reservations/[\w\-]+/loyalty(/.*)?` | ANY | staff-web | Yes | Yes | No (Curated out) | No | High | Add to SDK curated groups, migrate staff-web usage, remove from allowlist. |
| `/admin/settings/branches/export` | ANY | staff-web | Yes | No | No | No | Med | Keep in allowlist (deferred for Batch 12: Admin Export API Stabilization). |
| `/admin/benefits/(vouchers|loyalty-tiers)/export` | ANY | staff-web | Yes | No | No | No | Med | Keep in allowlist (deferred for Batch 12: Admin Export API Stabilization). |
| `/staff/reservations/` | ANY | staff-web | Yes | Yes | Yes | Yes | High | Template literal prefix. Will be naturally covered by migrating individual dynamic sub-routes to SDK. |
| `/staff/users/` | ANY | staff-web | Yes | Yes | Yes | No | High | Template literal prefix. Will be naturally covered by migrating individual dynamic sub-routes to SDK. |

## Preorder Flow Status
- **Backend Support:** Fully complete in PHP backend under Reservations module.
- **OpenAPI Parity:** Full contract is present in `openapi-v1.json` but excluded from TypeScript SDK generation.
- **Frontend App usage:** staff-web and customer-web have dynamic UI but staff-web called them manually using raw paths.
- **Planned Verification:** Include full preorder lifecycle (customer submit -> staff review -> confirm -> convert) in E2E smoke tests.

## Deposit/Payment Flow Status
- **Backend Support:** Active mock/simulated payment processor exists.
- **Planned Verification:** Verify payment/deposit intents, preview, sessions and confirmations using simulated provider in smoke test.
