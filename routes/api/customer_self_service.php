<?php

use App\Http\Controllers\Api\CustomerMenuCatalogController;
use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Modules\BenefitsLoyalty\Http\Controllers\Customer\CustomerBenefitsController;
use App\Modules\BenefitsLoyalty\Http\Controllers\Customer\CustomerReservationBenefitsActionController;
use App\Modules\BranchScheduling\Http\Controllers\TableController;
use App\Modules\BranchScheduling\Http\Controllers\TableHoldController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationBillPaymentController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationDepositPaymentController;
use App\Modules\CheckoutPayments\Http\Controllers\Customer\CustomerReservationOrderBillController;
use App\Modules\PrivacyAudit\Http\Controllers\Customer\CustomerDataLifecycleController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationSelfServiceController;
use App\Modules\Reservations\Http\Controllers\ReservationController;
use App\Modules\WaitingList\Http\Controllers\Customer\CustomerWaitingListController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    MetricsRequestMiddleware::class,
    ResolveCustomerAuthMiddleware::class,
])->group(function () {
    Route::get('menu/categories', [CustomerMenuCatalogController::class, 'categories']);
    Route::get('menu/items', [CustomerMenuCatalogController::class, 'items']);
    Route::get('menu/items/{id}', [CustomerMenuCatalogController::class, 'show'])->whereNumber('id');
    Route::post('menu/preorder/preview', [CustomerMenuCatalogController::class, 'previewPreorder']);

    Route::middleware(['require.redis'])->group(function () {
        Route::middleware([CustomerOrStaffMiddleware::class])->group(function () {
            Route::get('me/loyalty', [CustomerBenefitsController::class, 'loyalty']);
            Route::get('me/vouchers', [CustomerBenefitsController::class, 'vouchers']);
            Route::get('me/data-export', [CustomerDataLifecycleController::class, 'export']);
            Route::get('me/privacy-requests', [CustomerDataLifecycleController::class, 'index']);
            Route::post('me/privacy-requests', [CustomerDataLifecycleController::class, 'store'])
                ->middleware('idempotency:customer.privacy-requests.store');

            Route::get('reservations', [CustomerReservationSelfServiceController::class, 'index']);
            Route::post('reservations/{id}/cancel', [CustomerReservationSelfServiceController::class, 'cancel'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.cancel');
            Route::post('reservations/{id}/reschedule', [CustomerReservationSelfServiceController::class, 'reschedule'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.reschedule');

            Route::get('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'show'])->whereNumber('id');
            Route::post('reservations/{id}/preorder/preview', [CustomerReservationPreorderController::class, 'preview'])->whereNumber('id');
            Route::put('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'replace'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.replace');
            Route::delete('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'clear'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.clear');

            Route::get('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'show'])->whereNumber('id');
            Route::post('reservations/{id}/pre-order/preview', [CustomerReservationPreorderController::class, 'preview'])->whereNumber('id');
            Route::put('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'replace'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.replace');
            Route::delete('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'clear'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.clear');

            Route::get('reservations/{id}/deposit-preview', [CustomerReservationDepositController::class, 'show'])->whereNumber('id');
            Route::post('reservations/{id}/deposit/acknowledge', [CustomerReservationDepositController::class, 'acknowledge'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-deposit.acknowledge');
            Route::post('reservations/{id}/deposit/intent', [CustomerReservationDepositController::class, 'submitIntent'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-deposit.submit-intent');
            Route::post('reservations/{id}/deposit/intent/revoke', [CustomerReservationDepositController::class, 'revokeIntent'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-deposit.revoke-intent');

            Route::post('reservations/{reservation_id}/deposit/payment-sessions', [CustomerReservationDepositPaymentController::class, 'store'])
                ->whereNumber('reservation_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.create');
            Route::get('reservations/{reservation_id}/deposit/payment-sessions/{session_id}', [CustomerReservationDepositPaymentController::class, 'show'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id');
            Route::post('reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh', [CustomerReservationDepositPaymentController::class, 'refresh'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.refresh');
            Route::post('reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm', [CustomerReservationDepositPaymentController::class, 'confirm'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.confirm');

            Route::get('reservations/{id}/benefits-preview', [CustomerBenefitsController::class, 'reservationBenefitsPreview'])->whereNumber('id');
            Route::post('reservations/{id}/voucher/apply', [CustomerReservationBenefitsActionController::class, 'applyVoucher'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-voucher.apply');
            Route::post('reservations/{id}/voucher/remove', [CustomerReservationBenefitsActionController::class, 'removeVoucher'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-voucher.remove');
            Route::post('reservations/{id}/loyalty/redeem', [CustomerReservationBenefitsActionController::class, 'redeemLoyalty'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-loyalty.redeem');
            Route::post('reservations/{id}/loyalty/redeem/release', [CustomerReservationBenefitsActionController::class, 'releaseLoyalty'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-loyalty.release');

            Route::get('reservations/{reservation_id}/bill', [CustomerReservationOrderBillController::class, 'show'])
                ->whereNumber('reservation_id');
            Route::get('reservations/{reservation_id}/active-order', [CustomerReservationOrderBillController::class, 'activeOrder'])
                ->whereNumber('reservation_id');
            Route::get('reservations/{reservation_id}/bill-preview', [CustomerReservationOrderBillController::class, 'billPreview'])
                ->whereNumber('reservation_id');

            Route::post('reservations/{reservation_id}/bill/payment-sessions', [CustomerReservationBillPaymentController::class, 'store'])
                ->whereNumber('reservation_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.create');
            Route::get('reservations/{reservation_id}/bill/payment-sessions/{session_id}', [CustomerReservationBillPaymentController::class, 'show'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id');
            Route::post('reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh', [CustomerReservationBillPaymentController::class, 'refresh'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.refresh');
            Route::post('reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm', [CustomerReservationBillPaymentController::class, 'confirm'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.confirm');
        });

        Route::get('waiting-list', [CustomerWaitingListController::class, 'index']);
        Route::post('waiting-list', [CustomerWaitingListController::class, 'store'])
            ->middleware('idempotency:customer.waiting-list.create');
        Route::get('waiting-list/{id}', [CustomerWaitingListController::class, 'show'])->whereNumber('id');
        Route::post('waiting-list/{id}/accept', [CustomerWaitingListController::class, 'accept'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.accept');
        Route::post('waiting-list/{id}/confirm-arrival', [CustomerWaitingListController::class, 'confirmArrival'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.confirm-arrival');
        Route::post('waiting-list/{id}/decline', [CustomerWaitingListController::class, 'decline'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.decline');
        Route::post('waiting-list/{id}/cancel', [CustomerWaitingListController::class, 'cancel'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.cancel');

        Route::get('tables/available', [TableController::class, 'available'])
            ->middleware('redis.throttle:tables.available,'.
                config('booking.throttle_tables_available_limit').','.
                config('booking.throttle_tables_available_window').',ip'
            );

        Route::post('table-holds', [TableHoldController::class, 'store'])
            ->middleware([
                'redis.throttle:tableholds.store,'.
                    config('booking.throttle_table_holds_store_limit').','.
                    config('booking.throttle_table_holds_store_window').',ip',
                'hold.ratelimit',
                'idempotency:table-holds',
            ]);

        Route::get('table-holds/{hold_id}', [TableHoldController::class, 'show'])
            ->middleware('redis.throttle:tableholds.show,120,60,ip');

        Route::delete('table-holds/{hold_id}', [TableHoldController::class, 'cancel'])
            ->middleware([
                'redis.throttle:tableholds.cancel,60,60,ip',
                'idempotency:table-holds.cancel',
            ]);

        Route::patch('table-holds/{hold_id}/refresh', [TableHoldController::class, 'refresh'])
            ->middleware([
                'redis.throttle:tableholds.refresh,60,60,ip',
                'idempotency:table-holds.refresh',
            ]);

        Route::post('reservations', [ReservationController::class, 'store'])
            ->middleware([
                CustomerOrStaffMiddleware::class,
                'redis.throttle:reservations.store,'.
                    config('booking.throttle_reservations_store_limit').','.
                    config('booking.throttle_reservations_store_window').',either',
                'idempotency:reservations',
            ]);

        Route::get('reservations/{id}', [ReservationController::class, 'show'])
            ->whereNumber('id')
            ->middleware([
                CustomerOrStaffMiddleware::class,
                'redis.throttle:reservations.show,'.
                config('booking.throttle_reservations_show_limit').','.
                config('booking.throttle_reservations_show_window').',either',
            ]);
    });
});
