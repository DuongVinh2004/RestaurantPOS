# Booking incident runbook

## Checkout/refund incident

1. Capture request ID and idempotency key.
2. Check `payments` rows for the reservation.
3. Compare API response with DB state for:
   - reservation status
   - order status
   - payment summary
4. Use `refund-preview` or `settlement-preview` before any manual retry.
5. Do not manually insert refund rows without reconciling `refund_of_payment_id` lineage.

## Outbox incident

1. Run `php artisan notifications:outbox-health --json`.
2. Inspect failed and stale-processing counts.
3. Confirm scheduler heartbeat is fresh.
4. Re-run `notifications:process-outbox --limit=...` only after confirming provider credentials and lock health.

## Session access / privacy incident

1. Treat leaked `session_id` as sensitive.
2. Validate whether the exposed API response was `session` scope.
3. `session` scope should not expose email/phone/current loyalty summary after this patch.
4. Rotate or invalidate affected customer session material in the client if needed.
