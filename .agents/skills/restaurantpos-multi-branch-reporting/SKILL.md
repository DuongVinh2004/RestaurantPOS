---
name: restaurantpos-multi-branch-reporting
description: Harden RestaurantPOS branch management, default branch behavior, reporting snapshot rebuilds, and staff reporting read models. Use when Codex changes branch settings, branch context resolution, reporting snapshot generation, daily sales or operations or inventory read models, or tests that protect multi-branch scope and empty-scope reporting behavior.
---

# RestaurantPOS Multi-Branch Reporting

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/paths.md` before patching.

## Workflow

1. Decide whether the batch is about branch foundation, reporting snapshot rebuilds, or staff reporting reads.
2. Preserve default single-site behavior unless the caller explicitly sets a different `branch_id`.
3. Keep snapshot rebuild logic in `ReportingSnapshotService` and branch-resolution logic in `app/Services/Branch/*`.
4. When branch settings and reporting flows move together, stage the shared-file or controller seam last.
5. If the change alters route shape or admin or staff API payloads, combine with `restaurantpos-api-contract-gates`.

## Guardrails

- Do not silently mix multiple branches into one snapshot scope.
- Keep admin rebuild permissions and staff read permissions distinct.
- Empty or degraded snapshot scopes should surface warnings or degraded metadata, not silent success.
- Be careful with `config/booking.php` because branch defaults and reporting config share that seam.

## Verify

- `php artisan test tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php`
- `php artisan test tests/Feature/Admin/AdminReportingReadModelsFoundationHttpFlowTest.php tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php`
- `php artisan test tests/Feature/Admin/AdminReportingAndMultiBranchRouteSurfaceTest.php tests/Unit/Config/BookingReportingAndMultiBranchConfigContractTest.php tests/Unit/Services/Branch/BranchContextServiceTest.php`
