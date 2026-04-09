# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md` (Task E)
- `docs/admin-master-data-bulk.md`

## Code hotspots

- `app/Http/Controllers/Api/Admin/AdminInventoryController.php`
- `app/Http/Controllers/Api/Admin/AdminPurchasingController.php`
- `app/Services/Admin/AdminInventoryService.php`
- `app/Services/Admin/AdminPurchasingService.php`
- `app/Services/Inventory/InventoryStockMovementService.php`
- `app/Services/Inventory/OrderItemInventoryConsumptionService.php`
- `app/Models/*` related to ingredients, recipes, purchasing, and stock movements

## Test surface

- `tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php`
- `tests/Feature/Admin/AdminPurchasingFoundationHttpFlowTest.php`
- `tests/Feature/Admin/AdminInventoryKitchenPurchasingIdempotencyPolicyTest.php`
- `tests/Feature/Admin/AdminInventoryKitchenPurchasingRouteSurfaceTest.php`
- `tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php`

## Questions to answer before patching

- What prevents over-receiving or double-posting this stock movement?
- Which unit conversions are allowed and which are implicit bugs?
- How is branch scope carried through the purchase and receiving flow?
- Which downstream order or kitchen behavior depends on this inventory state?
