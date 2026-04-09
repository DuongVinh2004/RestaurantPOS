# Smoke Matrix

## Core health

- `php artisan booking:doctor --json`
- `php artisan notifications:outbox-health --json`
- `php artisan booking:release-manifest --json`
- `php artisan booking:deploy-check --mode=preflight --strict`

## Use these live slices by domain

- Reservation and FOH: `tests/Feature/Reservation/ReservationHttpFlowTest.php`, `tests/Feature/Staff/StaffReservationInboxFlowTest.php`, `tests/Feature/Staff/StaffCheckInFlowTest.php`
- Waiting list: `tests/Feature/Staff/StaffWaitingListLifecycleTest.php`, `tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php`
- Checkout and finance: `tests/Feature/Staff/StaffCheckoutHttpFlowTest.php`, `tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php`, `tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php`
- Customer payment: `tests/Feature/Payments/PaymentProviderWebhookFlowTest.php`, `tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php`, `tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php`

## Runtime prerequisites

- Redis must answer `PONG`
- Scheduler heartbeat must go fresh after `php artisan schedule:work`
- MySQL bootstrap must come from `composer bootstrap:booking`
