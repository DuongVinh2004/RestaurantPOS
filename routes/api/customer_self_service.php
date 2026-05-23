<?php

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Modules\Billing\Http\Controllers\Customer\ReservationBillController;
use App\Modules\BranchScheduling\Http\Controllers\Guest\RestaurantProfileController;
use App\Modules\BranchScheduling\Http\Controllers\Guest\TableAvailabilityController;
use App\Modules\BranchScheduling\Http\Controllers\Guest\TableHoldController;
use App\Modules\Catalog\Http\Controllers\Customer\MenuCatalogController;
use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltySummaryController;
use App\Modules\Loyalty\Http\Controllers\Customer\ReservationLoyaltyController;
use App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController;
use App\Modules\Payments\Http\Controllers\Customer\ReservationDepositPaymentController;
use App\Modules\PrivacyCompliance\Http\Controllers\Customer\PrivacyRequestController;
use App\Modules\Promotions\Http\Controllers\Customer\BenefitsController;
use App\Modules\Promotions\Http\Controllers\Customer\ReservationBenefitsActionController;
use App\Modules\Reservations\Http\Controllers\Customer\ReservationController;
use App\Modules\Reservations\Http\Controllers\Customer\ReservationSelfServiceController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationDepositController;
use App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController;
use App\Modules\Waitlist\Http\Controllers\Customer\WaitlistController as CustomerWaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    MetricsRequestMiddleware::class,
    ResolveCustomerAuthMiddleware::class,
])->group(function () {
    Route::get('restaurant/profile', [RestaurantProfileController::class, 'show']);

    Route::get('menu/categories', [MenuCatalogController::class, 'categories']);
    Route::get('menu/items', [MenuCatalogController::class, 'items']);
    Route::get('menu/items/{id}', [MenuCatalogController::class, 'show'])->whereNumber('id');
    Route::post('menu/preorder/preview', [MenuCatalogController::class, 'previewPreorder']);

    Route::get('qr/bill-preview/{token}', [\App\Modules\Billing\Http\Controllers\Customer\QrBillPreviewController::class, 'show'])
        ->middleware('redis.throttle:qr_bill_preview,60,1,ip');

    Route::middleware(['require.redis'])->group(function () {
        Route::middleware([CustomerOrStaffMiddleware::class])->group(function () {
            Route::get('me/loyalty', [LoyaltySummaryController::class, 'show']);
            Route::get('me/vouchers', [BenefitsController::class, 'vouchers']);
            Route::get('me/data-export', [PrivacyRequestController::class, 'export']);
            Route::get('me/privacy-requests', [PrivacyRequestController::class, 'index']);
            Route::post('me/privacy-requests', [PrivacyRequestController::class, 'store'])
                ->middleware('idempotency:customer.privacy-requests.store');

            Route::get('reservations', [ReservationSelfServiceController::class, 'index']);
            Route::post('reservations/{id}/cancel', [ReservationSelfServiceController::class, 'cancel'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.cancel');
            Route::post('reservations/{id}/reschedule', [ReservationSelfServiceController::class, 'reschedule'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.reschedule');

            Route::get('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'show'])->whereNumber('id');
            Route::post('reservations/{id}/preorder/preview', [CustomerReservationPreorderController::class, 'preview'])->whereNumber('id');
            Route::put('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'replace'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.replace');
            Route::post('reservations/{id}/preorder/submit', [CustomerReservationPreorderController::class, 'submit'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.submit');
            Route::delete('reservations/{id}/preorder', [CustomerReservationPreorderController::class, 'clear'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.clear');

            /** @deprecated Retained for compatibility. Use preorder instead. */
            Route::get('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'show'])->whereNumber('id')->name('customer.reservations.pre_order.show.legacy');
            /** @deprecated Retained for compatibility. Use preorder instead. */
            Route::post('reservations/{id}/pre-order/preview', [CustomerReservationPreorderController::class, 'preview'])->whereNumber('id')->name('customer.reservations.pre_order.preview.legacy');
            /** @deprecated Retained for compatibility. Use preorder instead. */
            Route::put('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'replace'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.replace')->name('customer.reservations.pre_order.replace.legacy');
            /** @deprecated Retained for compatibility. Use preorder instead. */
            Route::post('reservations/{id}/pre-order/submit', [CustomerReservationPreorderController::class, 'submit'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.submit')->name('customer.reservations.pre_order.submit.legacy');
            /** @deprecated Retained for compatibility. Use preorder instead. */
            Route::delete('reservations/{id}/pre-order', [CustomerReservationPreorderController::class, 'clear'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservations.preorder.clear')->name('customer.reservations.pre_order.clear.legacy');

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

            Route::post('reservations/{reservation_id}/deposit/payment-sessions', [ReservationDepositPaymentController::class, 'store'])
                ->whereNumber('reservation_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.create');
            Route::get('reservations/{reservation_id}/deposit/payment-sessions/{session_id}', [ReservationDepositPaymentController::class, 'show'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id');
            Route::post('reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh', [ReservationDepositPaymentController::class, 'refresh'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.refresh');
            Route::post('reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm', [ReservationDepositPaymentController::class, 'confirm'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-deposit-payment-sessions.confirm');

            Route::get('reservations/{id}/benefits-preview', [BenefitsController::class, 'reservationBenefitsPreview'])->whereNumber('id');
            Route::post('reservations/{id}/voucher/apply', [ReservationBenefitsActionController::class, 'applyVoucher'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-voucher.apply');
            Route::post('reservations/{id}/voucher/remove', [ReservationBenefitsActionController::class, 'removeVoucher'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-voucher.remove');
            Route::post('reservations/{id}/loyalty/redeem', [ReservationLoyaltyController::class, 'redeem'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-loyalty.redeem');
            Route::post('reservations/{id}/loyalty/redeem/release', [ReservationLoyaltyController::class, 'release'])
                ->whereNumber('id')
                ->middleware('idempotency:customer.reservation-loyalty.release');

            Route::get('reservations/{reservation_id}/bill', [ReservationBillController::class, 'show'])
                ->whereNumber('reservation_id');
            Route::get('reservations/{reservation_id}/active-order', [ReservationBillController::class, 'activeOrder'])
                ->whereNumber('reservation_id');
            Route::get('reservations/{reservation_id}/bill-preview', [ReservationBillController::class, 'billPreview'])
                ->whereNumber('reservation_id');

            Route::post('reservations/{reservation_id}/bill/payment-sessions', [ReservationBillPaymentController::class, 'store'])
                ->whereNumber('reservation_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.create');
            Route::get('reservations/{reservation_id}/bill/payment-sessions/{session_id}', [ReservationBillPaymentController::class, 'show'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id');
            Route::post('reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh', [ReservationBillPaymentController::class, 'refresh'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.refresh');
            Route::post('reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm', [ReservationBillPaymentController::class, 'confirm'])
                ->whereNumber('reservation_id')
                ->whereNumber('session_id')
                ->middleware('idempotency:customer.reservation-bill-payment-sessions.confirm');
        });

        Route::get('waiting-list', [CustomerWaitlistController::class, 'index']);
        Route::post('waiting-list', [CustomerWaitlistController::class, 'store'])
            ->middleware('idempotency:customer.waiting-list.create');
        Route::get('waiting-list/{id}', [CustomerWaitlistController::class, 'show'])->whereNumber('id');
        Route::post('waiting-list/{id}/accept', [CustomerWaitlistController::class, 'accept'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.accept');
        Route::post('waiting-list/{id}/confirm-arrival', [CustomerWaitlistController::class, 'confirmArrival'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.confirm-arrival');
        Route::post('waiting-list/{id}/decline', [CustomerWaitlistController::class, 'decline'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.decline');
        Route::post('waiting-list/{id}/cancel', [CustomerWaitlistController::class, 'cancel'])
            ->whereNumber('id')
            ->middleware('idempotency:customer.waiting-list.cancel');

        Route::get('tables/available', [TableAvailabilityController::class, 'available'])
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
