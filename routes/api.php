<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\Auth\StaffAuthController;
use App\Http\Controllers\Api\CustomerMenuCatalogController;
use App\Http\Controllers\Api\CustomerReservationPreorderController;
use App\Http\Controllers\Api\CustomerReservationSelfServiceController;
use App\Http\Controllers\Api\CustomerBenefitsController;
use App\Http\Controllers\Api\CustomerReservationBenefitsActionController;
use App\Http\Controllers\Api\CustomerDataLifecycleController;
use App\Http\Controllers\Api\CustomerWaitingListController;
use App\Http\Controllers\Api\CustomerReservationDepositController;
use App\Http\Controllers\Api\CustomerReservationDepositPaymentController;
use App\Http\Controllers\Api\CustomerReservationOrderBillController;
use App\Http\Controllers\Api\CustomerReservationBillPaymentController;
use App\Http\Controllers\Api\PaymentProviderWebhookController;
use App\Http\Controllers\Api\TableHoldController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\Staff\StaffTableBoardController;
use App\Http\Controllers\Api\Staff\StaffReservationCheckInController;
use App\Http\Controllers\Api\Staff\StaffServiceSessionController;
use App\Http\Controllers\Api\Staff\StaffTableOrderController;
use App\Http\Controllers\Api\Staff\StaffCheckoutController;
use App\Http\Controllers\Api\Staff\StaffTableReleaseController;
use App\Http\Controllers\Api\Staff\StaffWaitingListController;
use App\Http\Controllers\Api\Staff\StaffReservationRescheduleController;
use App\Http\Controllers\Api\Staff\StaffReservationMoveTableController;
use App\Http\Controllers\Api\Staff\StaffReservationVoucherController;
use App\Http\Controllers\Api\Staff\StaffLoyaltyController;
use App\Http\Controllers\Api\Admin\AdminBenefitSettingController;
use App\Http\Controllers\Api\Admin\AdminBranchController;
use App\Http\Controllers\Api\Admin\AdminFinanceSettingsController;
use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Http\Controllers\Api\Admin\AdminKitchenRoutingController;
use App\Http\Controllers\Api\Admin\AdminLoyaltyTierController;
use App\Http\Controllers\Api\Admin\AdminMenuCategoryController;
use App\Http\Controllers\Api\Admin\AdminMenuItemController;
use App\Http\Controllers\Api\Admin\AdminMenuItemPriceController;
use App\Http\Controllers\Api\Admin\AdminMasterDataBulkController;
use App\Http\Controllers\Api\Admin\AdminCustomerDataLifecycleController;
use App\Http\Controllers\Api\Admin\AdminPurchasingController;
use App\Http\Controllers\Api\Admin\AdminReportingController;
use App\Http\Controllers\Api\Admin\AdminRestaurantTableController;
use App\Http\Controllers\Api\Admin\AdminRestaurantZoneController;
use App\Http\Controllers\Api\Admin\AdminVoucherController;
use App\Http\Controllers\Api\Staff\StaffCashierShiftController;
use App\Http\Controllers\Api\Staff\StaffConversationInboxController;
use App\Http\Controllers\Api\Staff\StaffAuditTrailController;
use App\Http\Controllers\Api\Staff\StaffFinanceInvoiceController;
use App\Http\Controllers\Api\Staff\StaffFinancialReconciliationController;
use App\Http\Controllers\Api\Staff\StaffKitchenController;
use App\Http\Controllers\Api\Staff\StaffOperationalRealtimeController;
use App\Http\Controllers\Api\Staff\StaffOrderItemLifecycleController;
use App\Http\Controllers\Api\Staff\StaffOrderReadController;
use App\Http\Controllers\Api\Staff\StaffReportingController;
use App\Http\Controllers\Api\Staff\StaffReservationBoardAssignmentController;
use App\Http\Controllers\Api\Staff\StaffReservationDepositController;
use App\Http\Controllers\Api\Staff\StaffReservationInboxController;
use App\Http\Controllers\Api\Staff\StaffReservationTimelineController;
use App\Http\Controllers\Api\Staff\StaffReservationTimelineWorkbenchController;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;

Route::middleware([
    'reqid',
    'audit.request',
    ResolveCustomerAuthMiddleware::class,
    CustomerOrStaffMiddleware::class,
])->get('/user', function (Request $request) {
    if ($request->user()) {
        return response()->json([
            'auth_mode' => 'customer',
            'user' => [
                'user_id' => (int) $request->user()->user_id,
                'full_name' => (string) ($request->user()->full_name ?? ''),
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
                'role_id' => $request->user()->role_id,
                'current_tier_id' => $request->user()->current_tier_id,
            ],
        ]);
    }

    $staffActorUserId = $request->attributes->get('staff_actor_user_id');
    if ($staffActorUserId) {
        return response()->json([
            'auth_mode' => 'staff',
            'user' => [
                'user_id' => (int) $staffActorUserId,
                'role_id' => $request->attributes->get('staff_actor_role_id'),
                'role_name' => $request->attributes->get('staff_actor_role_name'),
                'staff_auth_mode' => $request->attributes->get('staff_auth_mode'),
            ],
        ]);
    }

    return response()->json(['message' => 'Unauthorized.'], 401);
});

Route::prefix('v1')
    ->middleware([
        'reqid',
        'audit.request',
    ])
    ->group(function () {
        require __DIR__.'/api/auth.php';
        require __DIR__.'/api/ops_release.php';
        require __DIR__.'/api/customer_self_service.php';
        require __DIR__.'/api/staff_pos.php';
        require __DIR__.'/api/admin.php';
    });
