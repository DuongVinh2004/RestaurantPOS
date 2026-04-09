# Round 5 canonical gate

This repository now treats the Round 5 financial integration gate as a canonical release artifact.
The source-of-truth suite definition lives in `tests/fixtures/round5_gate_suite.json`.

## What it covers

The gate is intentionally narrow and high-signal:

- checkout settlement / finalize replay safety
- refund + refund-cancel interplay
- loyalty earn / redeem / release / refund sync
- voucher consume / restore consistency
- payment summary integrity for deposit / final / refund flows
- payment provider adapter contract normalization
- deposit payment session runtime safety
- customer bill self-payment runtime safety
- webhook ingestion replay / signature / receipt handling
- reservation HTTP regressions that should stay green while Round 5 changes land

## Canonical command

Run the gate and persist a machine-readable snapshot:

```bash
php artisan booking:round5-gate --write
```

JSON output for CI / release evidence:

```bash
php artisan booking:round5-gate --write --json
```

The persisted snapshot is written to:

- `storage/app/booking_release/round5_gate_snapshot.json`

## Required suite members

At minimum the canonical gate includes:

- `tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php`
- `tests/Feature/Staff/StaffCheckoutFinancialOutboxAndCoverageTest.php`
- `tests/Feature/Staff/StaffCheckoutIdempotencyReplayServiceTest.php`
- `tests/Feature/Staff/StaffCheckoutConcurrencyGuardFlowTest.php`
- `tests/Feature/Payments/PaymentProviderWebhookFlowTest.php`
- `tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php`
- `tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php`
- `tests/Unit/Services/PaymentIntegration/GenericHttpHmacPaymentProviderAdapterTest.php`
- `tests/Unit/Services/Loyalty/LoyaltySyncServicesTest.php`
- `tests/Feature/Reservation/ReservationHttpFlowTest.php`
- `tests/Feature/Reservation/ReservationStatusHttpFlowTest.php`

## Release usage

Use this gate after applying cumulative checkout / loyalty / refund / payment-runtime patches and before promoting the build.
The saved snapshot should be attached to the release evidence together with:

- the frozen source bundle carrying both snapshot JSON files under `storage/app/booking_release/`
- `php artisan booking:release-manifest --write --json`
- `storage/app/booking_release/release_manifest_snapshot.json`
- `php artisan booking:deploy-check --mode=preflight --strict`
- `php artisan booking:deploy-check --mode=postflight --strict`
