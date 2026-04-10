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
use App\Http\Controllers\Api\Staff\StaffBranchContextController;
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
use App\Http\Controllers\Api\Staff\StaffMenuCatalogController;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
        Route::middleware([
            MetricsRequestMiddleware::class,
            ResolveCustomerAuthMiddleware::class,
        ])->group(function () {

            Route::middleware(['require.redis'])->group(function () {
                Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus'])
                    ->whereNumber('id')
                    ->middleware([
                        'redis.throttle:reservations.status,' .
                            config('booking.throttle_reservations_status_limit') . ',' .
                            config('booking.throttle_reservations_status_window') . ',either',
                        StaffApiKeyMiddleware::class,
                        'staff.capability:reservation.manage',
                        'idempotency:staff.reservation-status',
                    ]);

                Route::prefix('staff')
                    ->middleware([
                        StaffApiKeyMiddleware::class,
                        'redis.throttle:staff.ops,' .
                            config('booking.throttle_staff_limit', 300) . ',' .
                            config('booking.throttle_staff_window', 60) . ',either',
                    ])
                    ->group(function () {
                        Route::get('branches', [StaffBranchContextController::class, 'index']);
                        Route::get('menu/items', [StaffMenuCatalogController::class, 'index'])
                            ->middleware('staff.capability:order.manage');
                        Route::get('tables/board', [StaffTableBoardController::class, 'index'])->middleware('staff.capability:table.board.view');
                        Route::get('table-board', [StaffTableBoardController::class, 'legacyIndex'])->middleware('staff.capability:table.board.view');
                        Route::get('tables/{table_id}/active-service-session', [StaffServiceSessionController::class, 'showActiveByTable'])
                            ->whereNumber('table_id')
                            ->middleware('staff.capability:reservation.manage');

                        Route::post('service-sessions/walk-in', [StaffServiceSessionController::class, 'store'])
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.service-sessions.walk-in']);

                        Route::post('reservations/{id}/check-in', [StaffReservationCheckInController::class, 'store'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-checkin']);

                        Route::post('reservations/{id}/reschedule', [StaffReservationRescheduleController::class, 'store'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-reschedule']);

                        Route::post('reservations/{id}/move-table', [StaffReservationMoveTableController::class, 'store'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-move-table']);

                        Route::post('tables/{table_id}/orders', [StaffTableOrderController::class, 'store'])
                            ->whereNumber('table_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.table-orders']);

                        Route::post('orders/{order_id}/items', [StaffTableOrderController::class, 'addItems'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-items']);

                        Route::post('orders/{order_id}/close', [StaffCheckoutController::class, 'close'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-close']);

                        Route::post('orders/{order_id}/bill-snapshot', [StaffCheckoutController::class, 'billSnapshot'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-close']);

                        Route::get('orders/{order_id}/settlement-preview', [StaffCheckoutController::class, 'settlementPreview'])
                            ->whereNumber('order_id')
                            ->middleware('staff.capability:settlement.manage');

                        Route::post('orders/{order_id}/pay', [StaffCheckoutController::class, 'pay'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.order-pay']);

                        Route::post('orders/{order_id}/checkout', [StaffCheckoutController::class, 'checkout'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.checkout']);

                        Route::post('orders/{order_id}/settlement/finalize', [StaffCheckoutController::class, 'finalizeSettlement'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.checkout']);

                        Route::get('reservations/{reservation_id}/refund-preview', [StaffCheckoutController::class, 'refundPreview'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:payment.refund');

                        Route::post('reservations/{reservation_id}/refund', [StaffCheckoutController::class, 'refund'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:payment.refund', 'idempotency:staff.reservation-refund']);

                        Route::post('reservations/{reservation_id}/refund-cancel', [StaffCheckoutController::class, 'refundAndCancel'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:payment.refund', 'idempotency:staff.reservation-refund-cancel']);

                        Route::get('reservations/{reservation_id}/vouchers', [StaffReservationVoucherController::class, 'index'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:voucher.manage');

                        Route::post('reservations/{reservation_id}/voucher/apply', [StaffReservationVoucherController::class, 'apply'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-apply']);

                        Route::post('reservations/{reservation_id}/voucher/remove', [StaffReservationVoucherController::class, 'remove'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-remove']);

                        Route::post('reservations/{reservation_id}/voucher/release', [StaffReservationVoucherController::class, 'release'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-remove']);

                        Route::get('users/{user_id}/loyalty', [StaffLoyaltyController::class, 'showUser'])
                            ->whereNumber('user_id')
                            ->middleware('staff.capability:loyalty.view');

                        Route::post('users/{user_id}/loyalty/adjust', [StaffLoyaltyController::class, 'adjustUser'])
                            ->whereNumber('user_id')
                            ->middleware(['staff.capability:loyalty.adjust', 'idempotency:staff.user-loyalty-adjust']);

                        Route::get('reservations/{reservation_id}/loyalty', [StaffLoyaltyController::class, 'showReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:loyalty.view');

                        Route::post('reservations/{reservation_id}/loyalty/redeem', [StaffLoyaltyController::class, 'redeemReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-redeem']);

                        Route::post('reservations/{reservation_id}/loyalty/redeem/release', [StaffLoyaltyController::class, 'releaseReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-release']);

                        Route::post('reservations/{reservation_id}/loyalty/release', [StaffLoyaltyController::class, 'legacyReleaseReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-release']);

                        Route::post('tables/{table_id}/release', [StaffTableReleaseController::class, 'store'])
                            ->whereNumber('table_id')
                            ->middleware(['staff.capability:table.release', 'idempotency:staff.table-release']);

                        Route::get('waiting-list', [StaffWaitingListController::class, 'index'])->middleware('staff.capability:waiting_list.manage');
                        Route::post('waiting-list', [StaffWaitingListController::class, 'store'])
                            ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-create']);
                        Route::post('waiting-list/{id}/notify', [StaffWaitingListController::class, 'notify'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-notify']);
                        Route::post('waiting-list/{id}/seat', [StaffWaitingListController::class, 'seat'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-seat']);
                        Route::post('waiting-list/{id}/cancel', [StaffWaitingListController::class, 'cancel'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-cancel']);
                        Route::post('waiting-list/{id}/advance', [StaffWaitingListController::class, 'advance'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-advance']);
                        Route::get('waiting-list/changes', [StaffOperationalRealtimeController::class, 'waitingListChanges'])
                            ->middleware('staff.capability:waiting_list.manage');

                        Route::get('conversations', [StaffConversationInboxController::class, 'index'])
                            ->middleware('staff.capability:conversation.manage');
                        Route::get('conversations/{conversation_id}', [StaffConversationInboxController::class, 'show'])
                            ->whereUuid('conversation_id')
                            ->middleware('staff.capability:conversation.manage');
                        Route::post('conversations/{conversation_id}/assign', [StaffConversationInboxController::class, 'assign'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-assign']);
                        Route::post('conversations/{conversation_id}/take-over', [StaffConversationInboxController::class, 'takeOver'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-take-over']);
                        Route::post('conversations/{conversation_id}/unassign', [StaffConversationInboxController::class, 'unassign'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unassign']);
                        Route::post('conversations/{conversation_id}/links', [StaffConversationInboxController::class, 'link'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-link']);
                        Route::delete('conversations/{conversation_id}/links/reservation', [StaffConversationInboxController::class, 'unlinkReservation'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unlink-reservation']);
                        Route::delete('conversations/{conversation_id}/links/waiting-list', [StaffConversationInboxController::class, 'unlinkWaitingList'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unlink-waiting-list']);
                        Route::post('conversations/{conversation_id}/internal-notes', [StaffConversationInboxController::class, 'addInternalNote'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-internal-note']);
                        Route::post('conversations/{conversation_id}/outbound-replies', [StaffConversationInboxController::class, 'sendOutboundReply'])
                            ->whereUuid('conversation_id')
                            ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-outbound-reply']);

                        Route::get('reservations', [StaffReservationInboxController::class, 'index'])
                            ->middleware('staff.capability:reservation.manage');
                        Route::get('reservations/{reservation_id}', [StaffReservationInboxController::class, 'show'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:reservation.manage');
                        Route::get('reservations/{reservation_id}/orders', [StaffTableOrderController::class, 'indexByReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:order.manage');
                        Route::get('reservations/timeline', [StaffReservationTimelineController::class, 'index'])
                            ->middleware('staff.capability:reservation.manage');
                        Route::post('reservations/{id}/timeline/actions/assign-suggested', [StaffReservationTimelineWorkbenchController::class, 'assignSuggested'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-table']);
                        Route::post('reservations/{id}/timeline/actions/assign-best-fit', [StaffReservationTimelineWorkbenchController::class, 'assignBestFit'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-best-fit']);
                        Route::post('reservations/{id}/timeline/actions/check-in', [StaffReservationTimelineWorkbenchController::class, 'checkIn'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-checkin']);
                        Route::post('reservations/{id}/assign-table', [StaffReservationBoardAssignmentController::class, 'assignSuggested'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-table']);
                        Route::post('reservations/{id}/assign-best-fit', [StaffReservationBoardAssignmentController::class, 'assignBestFit'])
                            ->whereNumber('id')
                            ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-best-fit']);
                        Route::get('tables/board/changes', [StaffOperationalRealtimeController::class, 'boardChanges'])
                            ->middleware('staff.capability:table.board.view');

                        Route::get('orders/{order_id}', [StaffOrderReadController::class, 'show'])
                            ->whereNumber('order_id')
                            ->middleware('staff.capability:order.manage');
                        Route::get('tables/{table_id}/active-order', [StaffOrderReadController::class, 'showActiveByTable'])
                            ->whereNumber('table_id')
                            ->middleware('staff.capability:order.manage');
                        Route::get('reservations/{reservation_id}/active-order', [StaffOrderReadController::class, 'showActiveByReservation'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:order.manage');

                        Route::get('reservations/{reservation_id}/deposit-preview', [StaffReservationDepositController::class, 'preview'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:settlement.manage');
                        Route::post('reservations/{reservation_id}/deposit/pay', [StaffReservationDepositController::class, 'pay'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.reservation-deposit-pay']);

                        Route::get('cashier/shifts', [StaffCashierShiftController::class, 'index'])
                            ->middleware('staff.capability:cashier.shift.manage');
                        Route::get('cashier/shifts/current', [StaffCashierShiftController::class, 'current'])
                            ->middleware('staff.capability:cashier.shift.manage');
                        Route::post('cashier/shifts/open', [StaffCashierShiftController::class, 'open'])
                            ->middleware(['staff.capability:cashier.shift.manage', 'idempotency:staff.cashier-shift.open']);
                        Route::get('cashier/shifts/{shift_id}', [StaffCashierShiftController::class, 'show'])
                            ->whereNumber('shift_id')
                            ->middleware('staff.capability:cashier.shift.manage');
                        Route::post('cashier/shifts/{shift_id}/close', [StaffCashierShiftController::class, 'close'])
                            ->whereNumber('shift_id')
                            ->middleware(['staff.capability:cashier.shift.manage', 'idempotency:staff.cashier-shift.close']);

                        Route::get('finance/invoices/{reservation_id}', [StaffFinanceInvoiceController::class, 'show'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:settlement.manage');
                        Route::post('finance/invoices/{reservation_id}/issue', [StaffFinanceInvoiceController::class, 'issue'])
                            ->whereNumber('reservation_id')
                            ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.finance-invoice.issue']);
                        Route::get('finance/accounting-export', [StaffFinanceInvoiceController::class, 'accountingExport'])
                            ->middleware('staff.capability:settlement.manage');
                        Route::get('finance/reconciliation', [StaffFinancialReconciliationController::class, 'index'])
                            ->middleware('staff.capability:settlement.manage');
                        Route::get('finance/reconciliation/export', [StaffFinancialReconciliationController::class, 'export'])
                            ->middleware('staff.capability:settlement.manage');
                        Route::get('finance/reconciliation/{reservation_id}', [StaffFinancialReconciliationController::class, 'show'])
                            ->whereNumber('reservation_id')
                            ->middleware('staff.capability:settlement.manage');
                        Route::get('audit-trail', [StaffAuditTrailController::class, 'index'])
                            ->middleware('staff.capability:audit.view');
                        Route::get('reporting/daily-sales', [StaffReportingController::class, 'dailySales'])
                            ->middleware('staff.capability:reporting.view');
                        Route::get('reporting/daily-operations', [StaffReportingController::class, 'dailyOperations'])
                            ->middleware('staff.capability:reporting.view');
                        Route::get('reporting/daily-inventory', [StaffReportingController::class, 'dailyInventory'])
                            ->middleware('staff.capability:reporting.view');

                        Route::get('kitchen/stations', [StaffKitchenController::class, 'stations'])
                            ->middleware('staff.capability:kitchen.manage');
                        Route::get('kitchen/stations/{station_id}/tickets', [StaffKitchenController::class, 'stationTickets'])
                            ->whereNumber('station_id')
                            ->middleware('staff.capability:kitchen.manage');
                        Route::post('orders/{order_id}/kitchen/dispatch', [StaffKitchenController::class, 'dispatchOrder'])
                            ->whereNumber('order_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.kitchen.dispatch']);
                        Route::post('kitchen/tickets/{ticket_id}/fire', [StaffKitchenController::class, 'fire'])
                            ->whereNumber('ticket_id')
                            ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.fire']);
                        Route::post('kitchen/tickets/{ticket_id}/bump', [StaffKitchenController::class, 'bump'])
                            ->whereNumber('ticket_id')
                            ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.bump']);
                        Route::post('kitchen/tickets/{ticket_id}/recall', [StaffKitchenController::class, 'recall'])
                            ->whereNumber('ticket_id')
                            ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.recall']);
                        Route::get('kitchen/changes', [StaffKitchenController::class, 'changes'])
                            ->middleware('staff.capability:kitchen.manage');
                        Route::patch('orders/{order_id}/items/{order_item_id}', [StaffOrderItemLifecycleController::class, 'update'])
                            ->whereNumber('order_id')
                            ->whereNumber('order_item_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-item.update']);
                        Route::post('orders/{order_id}/items/{order_item_id}/status', [StaffOrderItemLifecycleController::class, 'updateStatus'])
                            ->whereNumber('order_id')
                            ->whereNumber('order_item_id')
                            ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-item.status']);
                    });
            });
        });
