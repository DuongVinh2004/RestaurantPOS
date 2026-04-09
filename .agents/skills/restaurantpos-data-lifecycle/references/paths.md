# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/data-lifecycle.md`

## Code hotspots

- `app/Http/Controllers/Api/Admin/AdminCustomerDataLifecycleController.php`
- `app/Services/DataLifecycle/CustomerDataExportService.php`
- `app/Services/DataLifecycle/CustomerPrivacyRequestService.php`
- `app/Services/DataLifecycle/CustomerAnonymizationService.php`
- `app/Services/DataLifecycle/DataRetentionService.php`

## Test surface

- `tests/Feature/DataLifecycle/CustomerDataLifecycleHttpFlowTest.php`
- `tests/Feature/DataLifecycle/DataLifecycleRetentionConsoleTest.php`
- `tests/Feature/DataLifecycle/DataLifecycleRouteSurfaceTest.php`

## Questions to answer before patching

- Is the requested action export, review, commit, or retention prune?
- Which tables must remain intact for audit or finance lineage?
- Which fields must be redacted or purged at the source row?
- Does the change alter customer-facing, admin-facing, or console behavior?
