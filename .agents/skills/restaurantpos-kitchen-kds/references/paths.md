# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md` (Task D)

## Code hotspots

- `app/Http/Controllers/Api/Staff/StaffKitchenController.php`
- `app/Http/Controllers/Api/Admin/AdminKitchenRoutingController.php`
- `app/Services/Kitchen/KitchenRoutingService.php`
- `app/Services/Staff/StaffOrderItemLifecycleService.php`
- `app/Services/Staff/StaffOperationalRealtimeService.php`
- `config/feature_flags.php`

## Test surface

- `tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php`
- `tests/Feature/Admin/AdminKitchenRoutingFoundationHttpFlowTest.php`
- `tests/Feature/Staff/StaffOperationalRealtimeFlowTest.php`
- `tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php`

## Questions to answer before patching

- Which item states map to active kitchen work today?
- What happens to active tickets when routing changes?
- Which feature flag or config gate controls this surface?
- Which negative test proves unrouted or terminal behavior is safe?
