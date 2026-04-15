<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\BranchScheduling\Http\Controllers\TableController;
use App\Modules\Reservations\Http\Controllers\ReservationController;
use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\Auth\StaffAuthController;
use App\Http\Controllers\Api\CustomerMenuCatalogController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationSelfServiceController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Customer\CustomerBenefitsController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Customer\CustomerReservationBenefitsActionController;
use App\Modules\PrivacyAudit\Http\Controllers\Customer\CustomerDataLifecycleController;
use App\Modules\WaitingList\Http\Controllers\Customer\CustomerWaitingListController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationDepositPaymentController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationOrderBillController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationBillPaymentController;
use App\Modules\CheckoutPayments\Http\Controllers\PaymentProviderWebhookController;
use App\Modules\BranchScheduling\Http\Controllers\TableHoldController;
use App\Platform\Health\Http\HealthController;
use App\Platform\Metrics\Http\MetricsController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffTableBoardController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffReservationCheckInController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffServiceSessionController;
use App\Modules\Ordering\Http\Controllers\Staff\StaffTableOrderController;
use App\Modules\CheckoutPayments\Http\Controllers\Staff\StaffCheckoutController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffTableReleaseController;
use App\Modules\WaitingList\Http\Controllers\Staff\StaffWaitingListController;
use App\Http\Controllers\Api\Staff\StaffReservationRescheduleController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffReservationMoveTableController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Staff\StaffReservationVoucherController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Staff\StaffLoyaltyController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminBenefitSettingController;
use App\Http\Controllers\Api\Admin\AdminBranchController;
use App\Http\Controllers\Api\Admin\AdminFinanceSettingsController;
use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Modules\KitchenDispatch\Http\Controllers\Admin\AdminKitchenRoutingController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminLoyaltyTierController;
use App\Http\Controllers\Api\Admin\AdminMenuCategoryController;
use App\Http\Controllers\Api\Admin\AdminMenuItemController;
use App\Http\Controllers\Api\Admin\AdminMenuItemPriceController;
use App\Modules\AdminMasterDataBulk\Http\Controllers\Admin\AdminMasterDataBulkController;
use App\Modules\PrivacyAudit\Http\Controllers\Admin\AdminCustomerDataLifecycleController;
use App\Http\Controllers\Api\Admin\AdminPurchasingController;
use App\Modules\Reporting\Http\Controllers\Admin\AdminReportingController;
use App\Http\Controllers\Api\Admin\AdminRestaurantTableController;
use App\Http\Controllers\Api\Admin\AdminRestaurantZoneController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Admin\AdminVoucherController;
use App\Modules\CheckoutPayments\Http\Controllers\Staff\StaffCashierShiftController;
use App\Modules\Conversations\Http\Controllers\Staff\StaffConversationInboxController;
use App\Modules\PrivacyAudit\Http\Controllers\Staff\StaffAuditTrailController;
use App\Modules\CheckoutPayments\Http\Controllers\Staff\StaffFinanceInvoiceController;
use App\Modules\CheckoutPayments\Http\Controllers\Staff\StaffFinancialReconciliationController;
use App\Modules\KitchenDispatch\Http\Controllers\Staff\StaffKitchenController;
use App\Modules\Reporting\Http\Controllers\Staff\StaffOperationalRealtimeController;
use App\Modules\Ordering\Http\Controllers\Staff\StaffOrderItemLifecycleController;
use App\Modules\Ordering\Http\Controllers\Staff\StaffOrderReadController;
use App\Modules\Reporting\Http\Controllers\Staff\StaffReportingController;
use App\Modules\FloorOps\Http\Controllers\Staff\StaffReservationBoardAssignmentController;
use App\Modules\CheckoutPayments\Http\Controllers\Staff\StaffReservationDepositController;
use App\Modules\Conversations\Http\Controllers\Staff\StaffReservationInboxController;
use App\Http\Controllers\Api\Staff\StaffReservationTimelineController;
use App\Http\Controllers\Api\Staff\StaffReservationTimelineWorkbenchController;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Support\ApiErrorResponse;

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

    return ApiErrorResponse::authenticationRequired($request, 'Authentication is required.');
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
