# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`

## Code hotspots

- `config/customer_auth.php`
- `app/Http/Middleware/ResolveCustomerAuthMiddleware.php`
- `app/Services/CustomerAccessSessionService.php`
- `app/Services/CustomerReservationSessionAccessService.php`
- `app/Services/Reservation/CustomerReservationSelfService.php`
- `app/Services/WaitingList/CustomerWaitingListSelfService.php`
- `app/Services/Customer/CustomerReservationDepositService.php`
- `app/Services/Customer/CustomerReservationDepositPaymentService.php`
- `app/Services/Customer/CustomerReservationBillPaymentService.php`
- `app/Services/ReservationDepositPaymentService.php`
- `app/Services/ReservationBillPaymentService.php`

## Test surface

- `tests/Feature/Auth/Customer*`
- `tests/Feature/Customer/*`
- `tests/Feature/Reservation/CustomerReservation*`
- `tests/Feature/WaitingList/CustomerWaitingList*`
- `tests/Feature/Services/CustomerReservationSessionAccessServiceTest.php`
- `tests/Unit/Config/CustomerAuthConfigContractTest.php`
- `tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php`

## Questions to answer before patching

- Which token or session proves ownership for this endpoint?
- What data must stay hidden from customer responses?
- What happens when the session is expired, revoked, or mismatched?
- Does self-pay behavior depend on provider rollout or simulated payment settings?
