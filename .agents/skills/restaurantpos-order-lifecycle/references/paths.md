# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/uat-demo-scenario-pack.md`

## Code hotspots

- `app/Http/Controllers/Api/Staff/StaffTableOrderController.php`
- `app/Http/Controllers/Api/Staff/StaffOrderItemLifecycleController.php`
- `app/Http/Controllers/Api/Staff/StaffOrderReadController.php`
- `app/Services/Staff/StaffTableOrderService.php`
- `app/Services/Staff/StaffOrderItemLifecycleService.php`
- `app/Services/Staff/StaffOrderReadService.php`
- `app/Services/Staff/StaffTableBoardService.php`
- `app/Services/Inventory/OrderItemInventoryConsumptionService.php`
- `app/Services/Kitchen/KitchenRoutingService.php`
- `app/Services/Staff/StaffCheckoutService.php`

## Test surface

- `tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php`
- `tests/Feature/Staff/StaffOrderReadFlowTest.php`
- `tests/Feature/Staff/StaffTableOrderBranchScopeTest.php`
- `tests/Feature/Staff/StaffTableOrderConcurrencyGuardServiceTest.php`
- `tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php`
- `tests/Feature/Staff/StaffTableBoardFeatureTest.php`
- `tests/Unit/Services/Staff/OrderSettlementServiceTest.php`

## Questions to answer before patching

- Which table or reservation state makes the order mutation legal?
- Which item transitions are terminal and which remain reversible?
- Does the mutation replay safely under the current idempotency key?
- Which downstream domain must stay in sync: kitchen, inventory, checkout, or all three?
