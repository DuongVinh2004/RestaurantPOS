<?php

use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Modules\Billing\Http\Controllers\Staff\InvoiceController;
use App\Modules\Cashiering\Http\Controllers\Staff\CashierShiftController;
use App\Modules\Cashiering\Http\Controllers\Staff\CheckoutController;
use App\Modules\Cashiering\Http\Controllers\Staff\SettlementReconciliationController;
use App\Modules\Catalog\Http\Controllers\Staff\MenuCatalogController;
use App\Modules\Conversations\Http\Controllers\Staff\ConversationInboxController;
use App\Modules\Conversations\Http\Controllers\Staff\ReservationInboxController;
use App\Modules\FloorOperations\Http\Controllers\Staff\BranchContextController;
use App\Modules\FloorOperations\Http\Controllers\Staff\OperationalChangeFeedController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ReservationBoardAssignmentController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ReservationCheckInController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ReservationMoveTableController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ReservationTimelineController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ReservationWorkbenchController;
use App\Modules\FloorOperations\Http\Controllers\Staff\ServiceSessionController;
use App\Modules\FloorOperations\Http\Controllers\Staff\TableBoardController;
use App\Modules\FloorOperations\Http\Controllers\Staff\TableReleaseController;
use App\Modules\KitchenDispatch\Http\Controllers\Staff\KitchenDispatchController;
use App\Modules\Loyalty\Http\Controllers\Staff\LoyaltyLedgerController;
use App\Modules\Ordering\Http\Controllers\Staff\OrderItemLifecycleController;
use App\Modules\Ordering\Http\Controllers\Staff\OrderReadController;
use App\Modules\Ordering\Http\Controllers\Staff\ReservationOrderController;
use App\Modules\Payments\Http\Controllers\Staff\ReservationDepositPaymentController;
use App\Modules\Payments\Http\Controllers\Staff\ReservationRefundController;
use App\Modules\PrivacyCompliance\Http\Controllers\Staff\AuditTrailController;
use App\Modules\Promotions\Http\Controllers\Staff\ReservationVoucherController;
use App\Modules\Reporting\Http\Controllers\Staff\InventoryReportController;
use App\Modules\Reporting\Http\Controllers\Staff\OperationsReportController;
use App\Modules\Reporting\Http\Controllers\Staff\SalesReportController;
use App\Modules\Reservations\Http\Controllers\Staff\ReservationController;
use App\Modules\Reservations\Http\Controllers\Staff\ReservationRescheduleController;
use App\Modules\Reservations\Http\Controllers\StaffReservationPreorderController;
use App\Modules\Waitlist\Http\Controllers\Staff\WaitlistController as StaffWaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    MetricsRequestMiddleware::class,
    ResolveCustomerAuthMiddleware::class,
])->group(function () {

    Route::middleware(['require.redis'])->group(function () {
        Route::patch('reservations/{id}/status', [ReservationController::class, 'updateStatus'])
            ->whereNumber('id')
            ->middleware([
                'redis.throttle:reservations.status,'.
                    config('booking.throttle_reservations_status_limit').','.
                    config('booking.throttle_reservations_status_window').',either',
                StaffApiKeyMiddleware::class,
                'staff.capability:reservation.manage',
                'idempotency:staff.reservation-status',
            ]);

        Route::prefix('staff')
            ->middleware([
                StaffApiKeyMiddleware::class,
                'redis.throttle:staff.ops,'.
                    config('booking.throttle_staff_limit', 300).','.
                    config('booking.throttle_staff_window', 60).',either',
            ])
            ->group(function () {
                Route::get('branches', [BranchContextController::class, 'index'])
                    ->middleware('staff.capability:reservation.manage');
                Route::get('operations/command-center', [\App\Modules\FloorOperations\Http\Controllers\Staff\CommandCenterController::class, 'index'])
                    ->middleware('staff.capability:reservation.manage');
                Route::get('menu/items', [MenuCatalogController::class, 'index'])
                    ->middleware('staff.capability:order.manage');
                Route::get('tables/board', [TableBoardController::class, 'index'])->middleware('staff.capability:table.board.view');
                Route::get('table-board', [TableBoardController::class, 'legacyIndex'])->middleware('staff.capability:table.board.view');
                Route::get('tables/{table_id}/active-service-session', [ServiceSessionController::class, 'showActiveByTable'])
                    ->whereNumber('table_id')
                    ->middleware('staff.capability:reservation.manage');

                Route::post('service-sessions/walk-in', [ServiceSessionController::class, 'store'])
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.service-sessions.walk-in']);

                Route::post('reservations/{id}/check-in', [ReservationCheckInController::class, 'store'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-checkin']);

                Route::post('reservations/{id}/reschedule', [ReservationRescheduleController::class, 'store'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-reschedule']);

                Route::post('reservations/{id}/move-table', [ReservationMoveTableController::class, 'store'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-move-table']);

                Route::post('tables/{table_id}/orders', [ReservationOrderController::class, 'store'])
                    ->whereNumber('table_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.table-orders']);

                Route::post('orders/{order_id}/items', [ReservationOrderController::class, 'addItems'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-items']);

                Route::post('orders/{order_id}/close', [CheckoutController::class, 'close'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-close']);

                Route::post('orders/{order_id}/bill-snapshot', [CheckoutController::class, 'billSnapshot'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-close']);

                Route::get('orders/{order_id}/settlement-preview', [CheckoutController::class, 'settlementPreview'])
                    ->whereNumber('order_id')
                    ->middleware('staff.capability:settlement.manage');

                Route::post('orders/{order_id}/pay', [CheckoutController::class, 'pay'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.order-pay']);

                Route::post('orders/{order_id}/checkout', [CheckoutController::class, 'checkout'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.checkout']);

                Route::post('orders/{order_id}/settlement/finalize', [CheckoutController::class, 'finalizeSettlement'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.checkout']);

                Route::get('reservations/{reservation_id}/refund-preview', [ReservationRefundController::class, 'preview'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:payment.refund');

                Route::post('reservations/{reservation_id}/refund', [ReservationRefundController::class, 'refund'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:payment.refund', 'idempotency:staff.reservation-refund']);

                Route::post('reservations/{reservation_id}/refund-cancel', [ReservationRefundController::class, 'refundAndCancel'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:payment.refund', 'idempotency:staff.reservation-refund-cancel']);

                Route::get('reservations/{reservation_id}/vouchers', [ReservationVoucherController::class, 'index'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:voucher.manage');

                Route::post('reservations/{reservation_id}/voucher/apply', [ReservationVoucherController::class, 'apply'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-apply']);

                Route::post('reservations/{reservation_id}/voucher/remove', [ReservationVoucherController::class, 'remove'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-remove']);

                Route::post('reservations/{reservation_id}/voucher/release', [ReservationVoucherController::class, 'release'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:voucher.manage', 'idempotency:staff.reservation-voucher-remove']);

                Route::get('users/{user_id}/loyalty', [LoyaltyLedgerController::class, 'showUser'])
                    ->whereNumber('user_id')
                    ->middleware('staff.capability:loyalty.view');

                Route::post('users/{user_id}/loyalty/adjust', [LoyaltyLedgerController::class, 'adjustUser'])
                    ->whereNumber('user_id')
                    ->middleware(['staff.capability:loyalty.adjust', 'idempotency:staff.user-loyalty-adjust']);

                Route::get('reservations/{reservation_id}/loyalty', [LoyaltyLedgerController::class, 'showReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:loyalty.view');

                Route::post('reservations/{reservation_id}/loyalty/redeem', [LoyaltyLedgerController::class, 'redeemReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-redeem']);

                Route::post('reservations/{reservation_id}/loyalty/redeem/release', [LoyaltyLedgerController::class, 'releaseReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-release']);

                Route::post('reservations/{reservation_id}/loyalty/release', [LoyaltyLedgerController::class, 'legacyReleaseReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:loyalty.redeem', 'idempotency:staff.reservation-loyalty-release']);

                Route::post('tables/{table_id}/release', [TableReleaseController::class, 'store'])
                    ->whereNumber('table_id')
                    ->middleware(['staff.capability:table.release', 'idempotency:staff.table-release']);

                Route::get('waiting-list', [StaffWaitlistController::class, 'index'])->middleware('staff.capability:waiting_list.manage');
                Route::post('waiting-list', [StaffWaitlistController::class, 'store'])
                    ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-create']);
                Route::post('waiting-list/{id}/notify', [StaffWaitlistController::class, 'notify'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-notify']);
                Route::post('waiting-list/{id}/seat', [StaffWaitlistController::class, 'seat'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-seat']);
                Route::post('waiting-list/{id}/cancel', [StaffWaitlistController::class, 'cancel'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-cancel']);
                Route::post('waiting-list/{id}/advance', [StaffWaitlistController::class, 'advance'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:waiting_list.manage', 'idempotency:staff.waiting-list-advance']);
                Route::get('waiting-list/changes', [OperationalChangeFeedController::class, 'waitingListChanges'])
                    ->middleware('staff.capability:waiting_list.manage');

                Route::get('conversations', [ConversationInboxController::class, 'index'])
                    ->middleware('staff.capability:conversation.manage');
                Route::get('conversations/{conversation_id}', [ConversationInboxController::class, 'show'])
                    ->whereUuid('conversation_id')
                    ->middleware('staff.capability:conversation.manage');
                Route::get('conversations/{conversation_id}/files/{file_id}/access', [ConversationInboxController::class, 'accessFile'])
                    ->whereUuid('conversation_id')
                    ->whereNumber('file_id')
                    ->middleware(['staff.capability:conversation.manage', 'signed'])
                    ->name('staff.conversations.files.access');
                Route::get('conversations/{conversation_id}/messages/{message_id}/attachment', [ConversationInboxController::class, 'accessMessageAttachment'])
                    ->whereUuid('conversation_id')
                    ->whereNumber('message_id')
                    ->middleware(['staff.capability:conversation.manage', 'signed'])
                    ->name('staff.conversations.messages.attachment');
                Route::post('conversations/{conversation_id}/assign', [ConversationInboxController::class, 'assign'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-assign']);
                Route::post('conversations/{conversation_id}/take-over', [ConversationInboxController::class, 'takeOver'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-take-over']);
                Route::post('conversations/{conversation_id}/unassign', [ConversationInboxController::class, 'unassign'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unassign']);
                Route::post('conversations/{conversation_id}/workflow-state', [ConversationInboxController::class, 'updateWorkflowState'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-workflow-state']);
                Route::post('conversations/{conversation_id}/links', [ConversationInboxController::class, 'link'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-link']);
                Route::delete('conversations/{conversation_id}/links/reservation', [ConversationInboxController::class, 'unlinkReservation'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unlink-reservation']);
                Route::delete('conversations/{conversation_id}/links/waiting-list', [ConversationInboxController::class, 'unlinkWaitingList'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-unlink-waiting-list']);
                Route::post('conversations/{conversation_id}/internal-notes', [ConversationInboxController::class, 'addInternalNote'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-internal-note']);
                Route::post('conversations/{conversation_id}/outbound-replies', [ConversationInboxController::class, 'sendOutboundReply'])
                    ->whereUuid('conversation_id')
                    ->middleware(['staff.capability:conversation.manage', 'idempotency:staff.conversation-outbound-reply']);

                Route::get('reservations', [ReservationInboxController::class, 'index'])
                    ->middleware('staff.capability:reservation.manage');
                Route::get('reservations/{reservation_id}', [ReservationInboxController::class, 'show'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:reservation.manage');
                Route::get('reservations/{reservation_id}/orders', [ReservationOrderController::class, 'indexByReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:order.manage');
                Route::get('reservations/timeline', [ReservationTimelineController::class, 'index'])
                    ->middleware('staff.capability:reservation.manage');
                Route::post('reservations/{id}/timeline/actions/assign-suggested', [ReservationWorkbenchController::class, 'assignSuggested'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-table']);
                Route::post('reservations/{id}/timeline/actions/assign-best-fit', [ReservationWorkbenchController::class, 'assignBestFit'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-best-fit']);
                Route::post('reservations/{id}/timeline/actions/check-in', [ReservationWorkbenchController::class, 'checkIn'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-checkin']);
                Route::post('reservations/{id}/assign-table', [ReservationBoardAssignmentController::class, 'assignSuggested'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-table']);
                Route::post('reservations/{id}/assign-best-fit', [ReservationBoardAssignmentController::class, 'assignBestFit'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-assign-best-fit']);
                Route::get('reservations/{id}/preorder', [StaffReservationPreorderController::class, 'show'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage']);
                Route::post('reservations/{id}/preorder/confirm', [StaffReservationPreorderController::class, 'confirm'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-preorder-confirm']);
                Route::post('reservations/{id}/preorder/reject', [StaffReservationPreorderController::class, 'reject'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-preorder-reject']);
                Route::post('reservations/{id}/preorder/convert', [StaffReservationPreorderController::class, 'convert'])
                    ->whereNumber('id')
                    ->middleware(['staff.capability:reservation.manage', 'idempotency:staff.reservation-preorder-convert']);
                Route::get('tables/board/changes', [OperationalChangeFeedController::class, 'boardChanges'])
                    ->middleware('staff.capability:table.board.view');

                Route::get('orders/{order_id}', [OrderReadController::class, 'show'])
                    ->whereNumber('order_id')
                    ->middleware('staff.capability:order.manage');
                Route::get('tables/{table_id}/active-order', [OrderReadController::class, 'showActiveByTable'])
                    ->whereNumber('table_id')
                    ->middleware('staff.capability:order.manage');
                Route::get('reservations/{reservation_id}/active-order', [OrderReadController::class, 'showActiveByReservation'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:order.manage');

                Route::get('reservations/{reservation_id}/deposit-preview', [ReservationDepositPaymentController::class, 'preview'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:settlement.manage');
                Route::post('reservations/{reservation_id}/deposit/pay', [ReservationDepositPaymentController::class, 'pay'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.reservation-deposit-pay']);

                Route::get('cashier/shifts', [CashierShiftController::class, 'index'])
                    ->middleware('staff.capability:cashier.shift.manage');
                Route::get('cashier/shifts/current', [CashierShiftController::class, 'current'])
                    ->middleware('staff.capability:cashier.shift.manage');
                Route::post('cashier/shifts/open', [CashierShiftController::class, 'open'])
                    ->middleware(['staff.capability:cashier.shift.manage', 'idempotency:staff.cashier-shift.open']);
                Route::get('cashier/shifts/{shift_id}', [CashierShiftController::class, 'show'])
                    ->whereNumber('shift_id')
                    ->middleware('staff.capability:cashier.shift.manage');
                Route::post('cashier/shifts/{shift_id}/close', [CashierShiftController::class, 'close'])
                    ->whereNumber('shift_id')
                    ->middleware(['staff.capability:cashier.shift.manage', 'idempotency:staff.cashier-shift.close']);

                Route::get('finance/invoices/{reservation_id}', [InvoiceController::class, 'show'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:settlement.manage');
                Route::post('finance/invoices/{reservation_id}/issue', [InvoiceController::class, 'issue'])
                    ->whereNumber('reservation_id')
                    ->middleware(['staff.capability:settlement.manage', 'idempotency:staff.finance-invoice.issue']);
                Route::get('finance/accounting-export', [InvoiceController::class, 'accountingExport'])
                    ->middleware('staff.capability:settlement.manage');
                Route::get('finance/reconciliation', [SettlementReconciliationController::class, 'index'])
                    ->middleware('staff.capability:settlement.manage');
                Route::get('finance/reconciliation/export', [SettlementReconciliationController::class, 'export'])
                    ->middleware('staff.capability:settlement.manage');
                Route::get('finance/reconciliation/{reservation_id}', [SettlementReconciliationController::class, 'show'])
                    ->whereNumber('reservation_id')
                    ->middleware('staff.capability:settlement.manage');
                Route::get('audit-trail', [AuditTrailController::class, 'index'])
                    ->middleware('staff.capability:audit.view');
                Route::get('reporting/daily-sales', [SalesReportController::class, 'index'])
                    ->middleware('staff.capability:reporting.view');
                Route::get('reporting/daily-operations', [OperationsReportController::class, 'index'])
                    ->middleware('staff.capability:reporting.view');
                Route::get('reporting/daily-inventory', [InventoryReportController::class, 'index'])
                    ->middleware('staff.capability:reporting.view');
                Route::get('reporting/analytics-overview', [\App\Modules\Reporting\Http\Controllers\Staff\AnalyticsOverviewController::class, 'index'])
                    ->middleware('staff.capability:reporting.view');

                Route::get('kitchen/stations', [KitchenDispatchController::class, 'stations'])
                    ->middleware('staff.capability:kitchen.manage');
                Route::get('kitchen/stations/{station_id}/tickets', [KitchenDispatchController::class, 'stationTickets'])
                    ->whereNumber('station_id')
                    ->middleware('staff.capability:kitchen.manage');
                Route::post('orders/{order_id}/kitchen/dispatch', [KitchenDispatchController::class, 'dispatchOrder'])
                    ->whereNumber('order_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.kitchen.dispatch']);
                Route::post('kitchen/tickets/{ticket_id}/fire', [KitchenDispatchController::class, 'fire'])
                    ->whereNumber('ticket_id')
                    ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.fire']);
                Route::post('kitchen/tickets/{ticket_id}/bump', [KitchenDispatchController::class, 'bump'])
                    ->whereNumber('ticket_id')
                    ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.bump']);
                Route::post('kitchen/tickets/{ticket_id}/recall', [KitchenDispatchController::class, 'recall'])
                    ->whereNumber('ticket_id')
                    ->middleware(['staff.capability:kitchen.manage', 'idempotency:staff.kitchen.recall']);
                Route::get('kitchen/changes', [KitchenDispatchController::class, 'changes'])
                    ->middleware('staff.capability:kitchen.manage');
                Route::patch('orders/{order_id}/items/{order_item_id}', [OrderItemLifecycleController::class, 'update'])
                    ->whereNumber('order_id')
                    ->whereNumber('order_item_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-item.update']);
                Route::post('orders/{order_id}/items/{order_item_id}/status', [OrderItemLifecycleController::class, 'updateStatus'])
                    ->whereNumber('order_id')
                    ->whereNumber('order_item_id')
                    ->middleware(['staff.capability:order.manage', 'idempotency:staff.order-item.status']);
            });
    });
});
