<?php

use App\Http\Controllers\Admin\UploadController;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Modules\Billing\Http\Controllers\Admin\FinanceTaxProfileController;
use App\Modules\BranchScheduling\Http\Controllers\Admin\BranchController;
use App\Modules\BranchScheduling\Http\Controllers\Admin\RestaurantTableController;
use App\Modules\BranchScheduling\Http\Controllers\Admin\RestaurantZoneController;
use App\Modules\BranchScheduling\Http\Controllers\Admin\TableTemplateController;
use App\Modules\Catalog\Http\Controllers\Admin\MenuCategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\MenuItemController;
use App\Modules\Catalog\Http\Controllers\Admin\MenuItemPriceController;
use App\Modules\Catalog\Http\Controllers\Admin\MenuModifierGroupController;
use App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController;
use App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController;
use App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenCategoryRouteController;
use App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenStationController;
use App\Modules\Loyalty\Http\Controllers\Admin\LoyaltyTierController;
use App\Modules\MasterDataExchange\Http\Controllers\Admin\MasterDataExportController;
use App\Modules\MasterDataExchange\Http\Controllers\Admin\MasterDataImportController;
use App\Modules\PrivacyCompliance\Http\Controllers\Admin\PrivacyController;
use App\Modules\Promotions\Http\Controllers\Admin\BenefitSettingController;
use App\Modules\Promotions\Http\Controllers\Admin\VoucherController;
use App\Modules\Reporting\Http\Controllers\Admin\ReportingSnapshotController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    MetricsRequestMiddleware::class,
    ResolveCustomerAuthMiddleware::class,
])->group(function () {

    Route::middleware(['require.redis'])->group(function () {
        Route::prefix('admin')
            ->middleware([
                StaffApiKeyMiddleware::class,
                'redis.throttle:staff.ops,'.
                    config('booking.throttle_staff_limit', 300).','.
                    config('booking.throttle_staff_window', 60).',either',
            ])
            ->group(function () {
                Route::post('upload', [UploadController::class, 'uploadImage'])
                    ->middleware('staff.capability:menu.manage');

                Route::middleware('staff.capability:privacy.manage')->prefix('privacy')->group(function () {
                    Route::get('requests', [PrivacyController::class, 'index']);
                    Route::get('customers/{user_id}/data-export', [PrivacyController::class, 'exportCustomerData'])
                        ->whereNumber('user_id');
                    Route::post('requests/{request_id}/review', [PrivacyController::class, 'review'])
                        ->whereNumber('request_id')
                        ->middleware('idempotency:admin.privacy-requests.review');
                });

                Route::middleware('staff.capability:menu.manage')->group(function () {
                    Route::get('menu/categories', [MenuCategoryController::class, 'index']);
                    Route::get('menu/categories/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'menu-categories');
                    Route::post('menu/categories/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'menu-categories')
                        ->middleware('idempotency:admin.menu-categories.import,mode,commit');
                    Route::post('menu/categories', [MenuCategoryController::class, 'store'])
                        ->middleware('idempotency:admin.menu-categories.store');
                    Route::patch('menu/categories/{category_id}', [MenuCategoryController::class, 'update'])
                        ->whereNumber('category_id')
                        ->middleware('idempotency:admin.menu-categories.update');

                    Route::get('menu/modifier-groups', [MenuModifierGroupController::class, 'index']);
                    Route::get('menu/modifier-groups/{modifierGroup}', [MenuModifierGroupController::class, 'show']);
                    Route::post('menu/modifier-groups', [MenuModifierGroupController::class, 'store'])
                        ->middleware('capability:menu.write');
                    Route::put('menu/modifier-groups/{modifierGroup}', [MenuModifierGroupController::class, 'update'])
                        ->middleware('capability:menu.write');
                    Route::delete('menu/modifier-groups/{modifierGroup}', [MenuModifierGroupController::class, 'destroy'])
                        ->middleware('capability:menu.write');

                    Route::get('menu/items', [MenuItemController::class, 'index']);
                    Route::get('menu/items/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'menu-items');
                    Route::post('menu/items/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'menu-items')
                        ->middleware('idempotency:admin.menu-items.import,mode,commit');
                    Route::get('menu/items/{item_id}', [MenuItemController::class, 'show'])->whereNumber('item_id');
                    Route::post('menu/items', [MenuItemController::class, 'store'])
                        ->middleware('idempotency:admin.menu-items.store');
                    Route::patch('menu/items/{item_id}', [MenuItemController::class, 'update'])
                        ->whereNumber('item_id')
                        ->middleware('idempotency:admin.menu-items.update');
                    Route::get('menu/prices/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'menu-prices');
                    Route::post('menu/prices/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'menu-prices')
                        ->middleware('idempotency:admin.prices.import,mode,commit');
                    Route::get('menu/items/{item_id}/prices', [MenuItemPriceController::class, 'index'])->whereNumber('item_id');
                    Route::get('menu/prices/{price_id}', [MenuItemPriceController::class, 'show'])->whereNumber('price_id');
                    Route::post('menu/items/{item_id}/prices', [MenuItemPriceController::class, 'store'])
                        ->whereNumber('item_id')
                        ->middleware('idempotency:admin.menu-item-prices.store');
                    Route::put('menu/prices/{price_id}', [MenuItemPriceController::class, 'update'])
                        ->whereNumber('price_id')
                        ->middleware('idempotency:admin.menu-item-prices.update');
                });

                Route::middleware('staff.capability:voucher.master_data.manage')->group(function () {
                    Route::get('benefits/vouchers', [VoucherController::class, 'index']);
                    Route::get('benefits/vouchers/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'vouchers');
                    Route::post('benefits/vouchers/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'vouchers')
                        ->middleware('idempotency:admin.master-data.import,mode,commit');
                    Route::get('benefits/vouchers/{id}', [VoucherController::class, 'show'])->whereNumber('id');
                    Route::post('benefits/vouchers', [VoucherController::class, 'store'])
                        ->middleware('idempotency:admin.benefits-vouchers.store');
                    Route::patch('benefits/vouchers/{id}', [VoucherController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.benefits-vouchers.update');
                    Route::get('benefits/loyalty-tiers', [LoyaltyTierController::class, 'index']);
                    Route::get('benefits/loyalty-tiers/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'loyalty-tiers');
                    Route::post('benefits/loyalty-tiers/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'loyalty-tiers')
                        ->middleware('idempotency:admin.master-data.import,mode,commit');
                    Route::get('benefits/loyalty-tiers/{id}', [LoyaltyTierController::class, 'show'])->whereNumber('id');
                    Route::post('benefits/loyalty-tiers', [LoyaltyTierController::class, 'store'])
                        ->middleware('idempotency:admin.loyalty-tiers.store');
                    Route::patch('benefits/loyalty-tiers/{id}', [LoyaltyTierController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.loyalty-tiers.update');
                    Route::get('settings/benefits', [BenefitSettingController::class, 'index']);
                    Route::post('settings/benefits', [BenefitSettingController::class, 'upsert'])
                        ->middleware('idempotency:admin.benefit-settings.upsert');
                });

                Route::middleware('staff.capability:settings.manage')->group(function () {
                    Route::get('settings/branches', [BranchController::class, 'index']);
                    Route::get('settings/branches/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'branches');
                    Route::post('settings/branches/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'branches')
                        ->middleware('idempotency:admin.branches.import,mode,commit');
                    Route::post('settings/branches', [BranchController::class, 'store'])
                        ->middleware('idempotency:admin.settings-branches.store');
                    Route::get('settings/branches/{id}', [BranchController::class, 'show'])->whereNumber('id');
                    Route::patch('settings/branches/{id}', [BranchController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.settings-branches.update');
                    Route::get('settings/finance/tax-profile', [FinanceTaxProfileController::class, 'showTaxProfile']);
                    Route::post('settings/finance/tax-profile', [FinanceTaxProfileController::class, 'upsertTaxProfile'])
                        ->middleware('idempotency:admin.settings-finance-tax-profile.upsert');
                    Route::post('settings/reporting/snapshots/rebuild', [ReportingSnapshotController::class, 'rebuild'])
                        ->middleware('idempotency:admin.reporting-snapshots.rebuild');
                    Route::get('kitchen/stations', [KitchenStationController::class, 'index']);
                    Route::post('kitchen/stations', [KitchenStationController::class, 'store'])
                        ->middleware('idempotency:admin.kitchen-stations.store');
                    Route::get('kitchen/stations/{station_id}', [KitchenStationController::class, 'show'])->whereNumber('station_id');
                    Route::patch('kitchen/stations/{station_id}', [KitchenStationController::class, 'update'])
                        ->whereNumber('station_id')
                        ->middleware('idempotency:admin.kitchen-stations.update');
                    Route::get('kitchen/stations/{station_id}/category-routes', [KitchenCategoryRouteController::class, 'index'])->whereNumber('station_id');
                    Route::put('kitchen/stations/{station_id}/category-routes', [KitchenCategoryRouteController::class, 'update'])
                        ->whereNumber('station_id')
                        ->middleware('idempotency:admin.kitchen-station-category-routes.sync');
                    Route::get('restaurant/zones', [RestaurantZoneController::class, 'index']);
                    Route::post('restaurant/zones/rename', [RestaurantZoneController::class, 'rename'])
                        ->middleware('idempotency:admin.restaurant-zones.rename');
                    Route::get('restaurant/tables', [RestaurantTableController::class, 'index']);
                    Route::get('restaurant/tables/export', [MasterDataExportController::class, 'export'])
                        ->defaults('domain', 'restaurant-tables');
                    Route::post('restaurant/tables/import', [MasterDataImportController::class, 'import'])
                        ->defaults('domain', 'restaurant-tables')
                        ->middleware('idempotency:admin.tables.import,mode,commit');
                    Route::post('restaurant/tables', [RestaurantTableController::class, 'store'])
                        ->middleware('idempotency:admin.restaurant-tables.store');
                    Route::get('restaurant/tables/{id}', [RestaurantTableController::class, 'show'])->whereNumber('id');
                    Route::patch('restaurant/tables/{id}', [RestaurantTableController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.restaurant-tables.update');
                    Route::delete('restaurant/tables/{id}', [RestaurantTableController::class, 'destroy'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.restaurant-tables.delete');
                    Route::get('restaurant/table-templates', [TableTemplateController::class, 'index']);
                });

                Route::middleware('staff.capability:inventory.manage')->group(function () {
                    Route::get('inventory/ingredients', [InventoryAdjustmentController::class, 'listIngredients']);
                    Route::post('inventory/ingredients', [InventoryAdjustmentController::class, 'createIngredient'])
                        ->middleware('idempotency:admin.inventory-ingredients.store');
                    Route::get('inventory/ingredients/{id}', [InventoryAdjustmentController::class, 'showIngredient'])->whereNumber('id');
                    Route::patch('inventory/ingredients/{id}', [InventoryAdjustmentController::class, 'updateIngredient'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-ingredients.update');
                    Route::get('inventory/menu-items/{id}/recipe', [InventoryAdjustmentController::class, 'showMenuItemRecipe'])->whereNumber('id');
                    Route::put('inventory/menu-items/{id}/recipe', [InventoryAdjustmentController::class, 'upsertMenuItemRecipe'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-menu-item-recipe.sync');
                    Route::get('inventory/ingredients/{id}/movements', [InventoryAdjustmentController::class, 'listIngredientMovements'])->whereNumber('id');
                    Route::post('inventory/ingredients/{id}/movements', [InventoryAdjustmentController::class, 'createIngredientMovement'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-movements.store');
                    Route::get('inventory/suppliers', [ProcurementController::class, 'listSuppliers']);
                    Route::post('inventory/suppliers', [ProcurementController::class, 'createSupplier'])
                        ->middleware('idempotency:admin.inventory-suppliers.store');
                    Route::get('inventory/suppliers/{id}', [ProcurementController::class, 'showSupplier'])->whereNumber('id');
                    Route::patch('inventory/suppliers/{id}', [ProcurementController::class, 'updateSupplier'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-suppliers.update');
                    Route::get('inventory/purchase-orders', [ProcurementController::class, 'listPurchaseOrders']);
                    Route::post('inventory/purchase-orders', [ProcurementController::class, 'createPurchaseOrder'])
                        ->middleware('idempotency:admin.inventory-purchase-orders.store');
                    Route::get('inventory/purchase-orders/{id}', [ProcurementController::class, 'showPurchaseOrder'])->whereNumber('id');
                    Route::patch('inventory/purchase-orders/{id}', [ProcurementController::class, 'updatePurchaseOrder'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-purchase-orders.update');
                    Route::get('inventory/purchase-orders/{id}/receipts', [ProcurementController::class, 'listPurchaseOrderReceipts'])->whereNumber('id');
                    Route::post('inventory/purchase-orders/{id}/receipts', [ProcurementController::class, 'createPurchaseOrderReceipt'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-purchase-order-receipts.store');
                });
            });
    });
});
