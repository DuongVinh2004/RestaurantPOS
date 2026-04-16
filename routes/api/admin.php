<?php

use App\Http\Controllers\Api\Admin\AdminBranchController;
use App\Http\Controllers\Api\Admin\AdminFinanceSettingsController;
use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Http\Controllers\Api\Admin\AdminMenuCategoryController;
use App\Http\Controllers\Api\Admin\AdminMenuItemController;
use App\Http\Controllers\Api\Admin\AdminMenuItemPriceController;
use App\Http\Controllers\Api\Admin\AdminPurchasingController;
use App\Http\Controllers\Api\Admin\AdminRestaurantTableController;
use App\Http\Controllers\Api\Admin\AdminRestaurantZoneController;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Modules\AdminMasterDataBulk\Http\Controllers\Admin\AdminMasterDataBulkController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminBenefitSettingController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminLoyaltyTierController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminVoucherController;
use App\Modules\KitchenDispatch\Http\Controllers\Admin\AdminKitchenRoutingController;
use App\Modules\PrivacyAudit\Http\Controllers\Admin\AdminCustomerDataLifecycleController;
use App\Modules\Reporting\Http\Controllers\Admin\AdminReportingController;
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
                Route::middleware('staff.capability:privacy.manage')->prefix('privacy')->group(function () {
                    Route::get('requests', [AdminCustomerDataLifecycleController::class, 'index']);
                    Route::get('customers/{user_id}/data-export', [AdminCustomerDataLifecycleController::class, 'exportCustomerData'])
                        ->whereNumber('user_id');
                    Route::post('requests/{request_id}/review', [AdminCustomerDataLifecycleController::class, 'review'])
                        ->whereNumber('request_id')
                        ->middleware('idempotency:admin.privacy-requests.review');
                });

                Route::middleware('staff.capability:menu.manage')->group(function () {
                    Route::get('menu/categories', [AdminMenuCategoryController::class, 'index']);
                    Route::get('menu/categories/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'menu-categories');
                    Route::post('menu/categories/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'menu-categories');
                    Route::post('menu/categories', [AdminMenuCategoryController::class, 'store'])
                        ->middleware('idempotency:admin.menu-categories.store');
                    Route::patch('menu/categories/{category_id}', [AdminMenuCategoryController::class, 'update'])
                        ->whereNumber('category_id')
                        ->middleware('idempotency:admin.menu-categories.update');
                    Route::get('menu/items', [AdminMenuItemController::class, 'index']);
                    Route::get('menu/items/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'menu-items');
                    Route::post('menu/items/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'menu-items');
                    Route::get('menu/items/{item_id}', [AdminMenuItemController::class, 'show'])->whereNumber('item_id');
                    Route::post('menu/items', [AdminMenuItemController::class, 'store'])
                        ->middleware('idempotency:admin.menu-items.store');
                    Route::patch('menu/items/{item_id}', [AdminMenuItemController::class, 'update'])
                        ->whereNumber('item_id')
                        ->middleware('idempotency:admin.menu-items.update');
                    Route::get('menu/prices/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'menu-prices');
                    Route::post('menu/prices/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'menu-prices');
                    Route::get('menu/items/{item_id}/prices', [AdminMenuItemPriceController::class, 'index'])->whereNumber('item_id');
                    Route::get('menu/prices/{price_id}', [AdminMenuItemPriceController::class, 'show'])->whereNumber('price_id');
                    Route::post('menu/items/{item_id}/prices', [AdminMenuItemPriceController::class, 'store'])
                        ->whereNumber('item_id')
                        ->middleware('idempotency:admin.menu-item-prices.store');
                    Route::put('menu/prices/{price_id}', [AdminMenuItemPriceController::class, 'update'])
                        ->whereNumber('price_id')
                        ->middleware('idempotency:admin.menu-item-prices.update');
                });

                Route::middleware('staff.capability:voucher.master_data.manage')->group(function () {
                    Route::get('benefits/vouchers', [AdminVoucherController::class, 'index']);
                    Route::get('benefits/vouchers/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'vouchers');
                    Route::post('benefits/vouchers/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'vouchers');
                    Route::get('benefits/vouchers/{id}', [AdminVoucherController::class, 'show'])->whereNumber('id');
                    Route::post('benefits/vouchers', [AdminVoucherController::class, 'store'])
                        ->middleware('idempotency:admin.benefits-vouchers.store');
                    Route::patch('benefits/vouchers/{id}', [AdminVoucherController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.benefits-vouchers.update');
                    Route::get('benefits/loyalty-tiers', [AdminLoyaltyTierController::class, 'index']);
                    Route::get('benefits/loyalty-tiers/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'loyalty-tiers');
                    Route::post('benefits/loyalty-tiers/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'loyalty-tiers');
                    Route::get('benefits/loyalty-tiers/{id}', [AdminLoyaltyTierController::class, 'show'])->whereNumber('id');
                    Route::post('benefits/loyalty-tiers', [AdminLoyaltyTierController::class, 'store'])
                        ->middleware('idempotency:admin.loyalty-tiers.store');
                    Route::patch('benefits/loyalty-tiers/{id}', [AdminLoyaltyTierController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.loyalty-tiers.update');
                    Route::get('settings/benefits', [AdminBenefitSettingController::class, 'index']);
                    Route::post('settings/benefits', [AdminBenefitSettingController::class, 'upsert'])
                        ->middleware('idempotency:admin.benefit-settings.upsert');
                });

                Route::middleware('staff.capability:settings.manage')->group(function () {
                    Route::get('settings/branches', [AdminBranchController::class, 'index']);
                    Route::get('settings/branches/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'branches');
                    Route::post('settings/branches/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'branches');
                    Route::post('settings/branches', [AdminBranchController::class, 'store'])
                        ->middleware('idempotency:admin.settings-branches.store');
                    Route::get('settings/branches/{id}', [AdminBranchController::class, 'show'])->whereNumber('id');
                    Route::patch('settings/branches/{id}', [AdminBranchController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.settings-branches.update');
                    Route::get('settings/finance/tax-profile', [AdminFinanceSettingsController::class, 'showTaxProfile']);
                    Route::post('settings/finance/tax-profile', [AdminFinanceSettingsController::class, 'upsertTaxProfile'])
                        ->middleware('idempotency:admin.settings-finance-tax-profile.upsert');
                    Route::post('settings/reporting/snapshots/rebuild', [AdminReportingController::class, 'rebuild'])
                        ->middleware('idempotency:admin.reporting-snapshots.rebuild');
                    Route::get('kitchen/stations', [AdminKitchenRoutingController::class, 'index']);
                    Route::post('kitchen/stations', [AdminKitchenRoutingController::class, 'store'])
                        ->middleware('idempotency:admin.kitchen-stations.store');
                    Route::get('kitchen/stations/{station_id}', [AdminKitchenRoutingController::class, 'show'])->whereNumber('station_id');
                    Route::patch('kitchen/stations/{station_id}', [AdminKitchenRoutingController::class, 'update'])
                        ->whereNumber('station_id')
                        ->middleware('idempotency:admin.kitchen-stations.update');
                    Route::get('kitchen/stations/{station_id}/category-routes', [AdminKitchenRoutingController::class, 'routes'])->whereNumber('station_id');
                    Route::put('kitchen/stations/{station_id}/category-routes', [AdminKitchenRoutingController::class, 'syncRoutes'])
                        ->whereNumber('station_id')
                        ->middleware('idempotency:admin.kitchen-station-category-routes.sync');
                    Route::get('restaurant/zones', [AdminRestaurantZoneController::class, 'index']);
                    Route::post('restaurant/zones/rename', [AdminRestaurantZoneController::class, 'rename'])
                        ->middleware('idempotency:admin.restaurant-zones.rename');
                    Route::get('restaurant/tables', [AdminRestaurantTableController::class, 'index']);
                    Route::get('restaurant/tables/export', [AdminMasterDataBulkController::class, 'export'])
                        ->defaults('domain', 'restaurant-tables');
                    Route::post('restaurant/tables/import', [AdminMasterDataBulkController::class, 'import'])
                        ->defaults('domain', 'restaurant-tables');
                    Route::post('restaurant/tables', [AdminRestaurantTableController::class, 'store'])
                        ->middleware('idempotency:admin.restaurant-tables.store');
                    Route::get('restaurant/tables/{id}', [AdminRestaurantTableController::class, 'show'])->whereNumber('id');
                    Route::patch('restaurant/tables/{id}', [AdminRestaurantTableController::class, 'update'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.restaurant-tables.update');
                    Route::delete('restaurant/tables/{id}', [AdminRestaurantTableController::class, 'destroy'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.restaurant-tables.delete');
                    Route::get('restaurant/table-templates', [AdminRestaurantTableController::class, 'templates']);
                });

                Route::middleware('staff.capability:inventory.manage')->group(function () {
                    Route::get('inventory/ingredients', [AdminInventoryController::class, 'listIngredients']);
                    Route::post('inventory/ingredients', [AdminInventoryController::class, 'createIngredient'])
                        ->middleware('idempotency:admin.inventory-ingredients.store');
                    Route::get('inventory/ingredients/{id}', [AdminInventoryController::class, 'showIngredient'])->whereNumber('id');
                    Route::patch('inventory/ingredients/{id}', [AdminInventoryController::class, 'updateIngredient'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-ingredients.update');
                    Route::get('inventory/menu-items/{id}/recipe', [AdminInventoryController::class, 'showMenuItemRecipe'])->whereNumber('id');
                    Route::put('inventory/menu-items/{id}/recipe', [AdminInventoryController::class, 'upsertMenuItemRecipe'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-menu-item-recipe.sync');
                    Route::get('inventory/ingredients/{id}/movements', [AdminInventoryController::class, 'listIngredientMovements'])->whereNumber('id');
                    Route::post('inventory/ingredients/{id}/movements', [AdminInventoryController::class, 'createIngredientMovement'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-movements.store');
                    Route::get('inventory/suppliers', [AdminPurchasingController::class, 'listSuppliers']);
                    Route::post('inventory/suppliers', [AdminPurchasingController::class, 'createSupplier'])
                        ->middleware('idempotency:admin.inventory-suppliers.store');
                    Route::get('inventory/suppliers/{id}', [AdminPurchasingController::class, 'showSupplier'])->whereNumber('id');
                    Route::patch('inventory/suppliers/{id}', [AdminPurchasingController::class, 'updateSupplier'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-suppliers.update');
                    Route::get('inventory/purchase-orders', [AdminPurchasingController::class, 'listPurchaseOrders']);
                    Route::post('inventory/purchase-orders', [AdminPurchasingController::class, 'createPurchaseOrder'])
                        ->middleware('idempotency:admin.inventory-purchase-orders.store');
                    Route::get('inventory/purchase-orders/{id}', [AdminPurchasingController::class, 'showPurchaseOrder'])->whereNumber('id');
                    Route::patch('inventory/purchase-orders/{id}', [AdminPurchasingController::class, 'updatePurchaseOrder'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-purchase-orders.update');
                    Route::get('inventory/purchase-orders/{id}/receipts', [AdminPurchasingController::class, 'listPurchaseOrderReceipts'])->whereNumber('id');
                    Route::post('inventory/purchase-orders/{id}/receipts', [AdminPurchasingController::class, 'createPurchaseOrderReceipt'])
                        ->whereNumber('id')
                        ->middleware('idempotency:admin.inventory-purchase-order-receipts.store');
                });
            });
    });
});
