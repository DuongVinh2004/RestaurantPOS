---
name: restaurantpos-checkout-finance
description: Harden checkout, settlement, refund, cashier shift, invoice, reconciliation, and payment integration flows in RestaurantPOS. Use when Codex changes settlement services, bill locks, payment capture, refund planning and execution, webhook ingestion, financial invariants, or tests for duplicate or replay and branch-scope finance paths.
---

# RestaurantPOS Checkout & Finance

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before editing.

## Workflow

1. Review the full financial lifecycle: preview -> lock bill -> capture or finalize -> refund -> refund cancel -> reconciliation.
2. Verify duplicate or replay handling, branch scope, refund lineage, stale write protection, and payment session ordering before patching.
3. Keep settlement math, capture rules, and refund planning in service code rather than controllers.
4. If provider or webhook behavior changes, update both runtime tests and contract or operational checks in the same batch.
5. Add focused regression coverage for race conditions and financial invariants.

## Guardrails

- Do not bypass bill locks or refund source checks to unblock a path.
- Preserve financial auditability and traceability across settlement and refund events.
- Treat webhook duplicate delivery and stale ordering as first-class cases, not edge cases.
- Keep API contract stable unless docs and contract tests move together.

## Verify

- `php artisan test tests/Feature/Staff/StaffCheckout*.php tests/Feature/Payments`
- `php artisan test tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php`
- Run targeted unit tests under `tests/Unit/Services/Staff` and `tests/Unit/Support` when changing settlement math or refund allocation.
