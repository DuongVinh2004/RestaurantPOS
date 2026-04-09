# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`

## Code hotspots

- `app/Http/Controllers/Api/Admin/AdminBranchController.php`
- `app/Http/Controllers/Api/Admin/AdminReportingController.php`
- `app/Http/Controllers/Api/Staff/StaffReportingController.php`
- `app/Services/Branch/BranchContextService.php`
- `app/Services/Branch/BranchManagementService.php`
- `app/Services/Reporting/ReportingSnapshotService.php`
- `config/booking.php`

## Test surface

- `tests/Feature/Admin/AdminMultiBranchFoundationHttpFlowTest.php`
- `tests/Feature/Admin/AdminMultiBranchDomainDefaultsHttpFlowTest.php`
- `tests/Feature/Admin/AdminReportingReadModelsFoundationHttpFlowTest.php`
- `tests/Feature/Admin/AdminReportingAndMultiBranchRouteSurfaceTest.php`
- `tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php`
- `tests/Unit/Config/BookingReportingAndMultiBranchConfigContractTest.php`
- `tests/Unit/Services/Branch/BranchContextServiceTest.php`

## Questions to answer before patching

- Is the change about branch CRUD, default-branch fallback, snapshot rebuild, or staff read models?
- Which branch scope should own the aggregate or response?
- Should empty scope be a warning, degraded result, or hard failure?
- Does the change alter admin-only or staff-readable contract surfaces?
