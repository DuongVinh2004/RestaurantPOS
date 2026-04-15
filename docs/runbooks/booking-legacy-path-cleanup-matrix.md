# Booking Legacy / Dead Path Cleanup Matrix

This matrix is now complemented by `docs/runbooks/post-refactor-migration-residue-report.md`, which is the current stabilization-era reference for removed compatibility shims and canonical route wiring.

This note documents the evidence-backed classification used by PATCH 6.

| Path / area | Classification | Evidence summary | Action in patch |
|---|---|---|---|
| `app/Http/Controllers/Api/CustomerWaitingListController.php` + `app/Services/CustomerWaitingListService.php` + `app/Http/Requests/Customer/*OwnerWaitingList*` | keep canonical | Wired from the customer waiting-list API route entrypoint (`routes/api.php` -> `routes/api/customer_self_service.php`); covered by owner contract / owner response tests | keep unchanged |
| `GET /api/v1/staff/tables/board` -> `StaffTableBoardController@index` | keep canonical | Locked by route inventory | keep unchanged |
| `GET /api/v1/staff/table-board` -> `StaffTableBoardController@legacyIndex` | keep alias | Still registered in routes, locked by route inventory, deprecation-header test proves compatibility intent | keep unchanged |
| `app/Services/StaffOrderReadController.php` | cleanup safe | No route reference, no controller wiring, no test reference, namespace/path mismatch declared the same FQCN as canonical controller | neutralized to tombstone |
| `app/Services/Staff/StaffOrderReadController.php` | cleanup safe | No route reference, no controller wiring, no test reference, namespace/path mismatch declared the same FQCN as canonical controller | neutralized to tombstone |
| `app/Services/WaitingList/CustomerWaitingListSelfService.php` | hold insufficient evidence | No current route/controller wiring found, but deletion could still be a compatibility break without explicit migration/removal proof | keep, mark deprecated |
| `app/Http/Requests/WaitingList/*CustomerWaitingList*` + `Concerns/AuthorizesCustomerWaitingListSelfService.php` | cleanup safe | No route/controller signature references remain in repo-local source; canonical flow is locked to `App\\Http\\Requests\\Customer` request classes by infrastructure coverage | removed |

## Notes

- Earlier Patch 6 avoided deleting PSR-4 classes that might still have out-of-repo consumers.
- The follow-up cleanup removes pure module/platform compatibility shims after internal callers were migrated to canonical classes.
- The cleanup also removes the legacy waiting-list request residue after infrastructure coverage was upgraded to lock the canonical controller request signatures.
- Anti-regression tests lock:
  - namespace/path parity under `app/`
  - canonical waiting-list owner controller wiring
  - canonical waiting-list owner controller request classes
  - compatibility status of the staff table-board alias
