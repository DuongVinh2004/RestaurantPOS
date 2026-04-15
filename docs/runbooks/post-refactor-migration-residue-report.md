# Post-Refactor Migration Residue Report

## Current policy

- Pure compatibility wrappers at the old `app/Http`, `app/Models`, `app/Services`, `app/Support`, and `app/Platform/Metrics` paths are no longer allowed once the target class exists under `App\Modules` or `App\Platform`.
- Canonical runtime wiring points at `App\Modules\...` or `App\Platform\...` classes directly.
- Old-path files stay only when they contain real, not-yet-migrated logic such as menu, inventory, branch admin, auth middleware, shared support, or API error helpers.

## Internal callers migrated off shims in stabilization

- Route include files now import canonical module controllers directly:
  - `routes/api/customer_self_service.php`
  - `routes/api/staff_pos.php`
  - `routes/api/admin.php`
  - `routes/api/auth.php`
  - `routes/api/ops_release.php`
- Batch 6 module internals now import canonical module namespaces instead of their own legacy aliases:
  - `app/Modules/WaitingList/...`
  - `app/Modules/Conversations/...`
  - `app/Modules/Notifications/...`
  - `app/Modules/PrivacyAudit/...`
  - `app/Modules/Reporting/...`
  - `app/Modules/AdminMasterDataBulk/...`
- Follow-up cleanup migrated remaining app, route, console, test, tool, and docs references off the old shim FQCNs before deleting the shim files.

## Removed compatibility residue

- Removed old controller, request, resource, model, service, support, and platform shim files that only forwarded to `App\Modules` or `App\Platform` classes through `class_alias`, `extends`, interface extension, or static delegation.
- Removed obsolete shim-forwarding unit tests; `tests/Unit/Infrastructure/LegacyPathCleanupEvidenceTest.php` now locks the cleanup by scanning `app/` for module/platform compatibility shim patterns.
- Empty directories left behind by shim deletion were pruned.

## Compatibility intentionally preserved

- Route aliases remain when they are API behavior, not file-level compatibility. Example: `GET /api/v1/staff/table-board` still routes to `StaffTableBoardController@legacyIndex` and remains covered as an explicit deprecated API alias.
- Old-path PHP files that still contain domain or platform-independent logic remain until their domains are migrated in a separate batch.
