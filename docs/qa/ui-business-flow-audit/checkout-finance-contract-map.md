# Checkout / Finance Contract Map

**Batch**: Checkout/Finance Complete Deep Audit  
**Branch**: `checkout-finance-complete-deep-audit`  
**Source**: Read from `routes/api/staff_pos.php` + all referenced controllers (2026-05-30)

---

## 1. Checkout / Payment Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/orders/{order_id}/settlement-preview` | `CheckoutController@settlementPreview` | — | `settlement.manage` |
| POST | `/api/v1/staff/orders/{order_id}/bill-snapshot` | `CheckoutController@billSnapshot` | `staff.order-close` | `order.manage` |
| POST | `/api/v1/staff/orders/{order_id}/close` | `CheckoutController@close` (**deprecated alias** → bill-snapshot) | `staff.order-close` | `order.manage` |
| POST | `/api/v1/staff/orders/{order_id}/pay` | `CheckoutController@pay` | `staff.order-pay` | `settlement.manage` |
| POST | `/api/v1/staff/orders/{order_id}/settlement/finalize` | `CheckoutController@finalizeSettlement` | `staff.checkout` | `settlement.manage` |
| POST | `/api/v1/staff/orders/{order_id}/checkout` | `CheckoutController@checkout` (**deprecated** → settlement/finalize) | `staff.checkout` | `settlement.manage` |

### Preferred Settlement Flow (two-step)
1. `POST bill-snapshot` — lock bill, capture snapshot
2. `POST pay` — pay against locked snapshot

### Legacy Single-Step Flow
- `POST checkout` / `POST settlement/finalize` — lock + pay atomically

---

## 2. Refund Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/reservations/{reservation_id}/refund-preview` | `ReservationRefundController@preview` | — | `payment.refund` |
| POST | `/api/v1/staff/reservations/{reservation_id}/refund` | `ReservationRefundController@refund` | `staff.reservation-refund` | `payment.refund` |
| POST | `/api/v1/staff/reservations/{reservation_id}/refund-cancel` | `ReservationRefundController@refundAndCancel` | `staff.reservation-refund-cancel` | `payment.refund` |

**Note**: Refund routes are scoped to `reservation_id` (not `order_id`). The walk-in session creates a reservation internally, and `reservation_id` is required for refund preview.

---

## 3. Cashier Shift Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/cashier/shifts` | `CashierShiftController@index` | — | `cashier.shift.manage` |
| GET | `/api/v1/staff/cashier/shifts/current` | `CashierShiftController@current` | — | `cashier.shift.manage` |
| POST | `/api/v1/staff/cashier/shifts/open` | `CashierShiftController@open` | `staff.cashier-shift.open` | `cashier.shift.manage` |
| GET | `/api/v1/staff/cashier/shifts/{shift_id}` | `CashierShiftController@show` | — | `cashier.shift.manage` |
| POST | `/api/v1/staff/cashier/shifts/{shift_id}/close` | `CashierShiftController@close` | `staff.cashier-shift.close` | `cashier.shift.manage` |

---

## 4. Finance / Reconciliation / Invoice Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/finance/invoices/{reservation_id}` | `InvoiceController@show` | — | `settlement.manage` |
| POST | `/api/v1/staff/finance/invoices/{reservation_id}/issue` | `InvoiceController@issue` | `staff.finance-invoice.issue` | `settlement.manage` |
| GET | `/api/v1/staff/finance/accounting-export` | `InvoiceController@accountingExport` | — | `settlement.manage` |
| GET | `/api/v1/staff/finance/reconciliation` | `SettlementReconciliationController@index` | — | `settlement.manage` |
| GET | `/api/v1/staff/finance/reconciliation/export` | `SettlementReconciliationController@export` | — | `settlement.manage` |
| GET | `/api/v1/staff/finance/reconciliation/{reservation_id}` | `SettlementReconciliationController@show` | — | `settlement.manage` |

---

## 5. Voucher Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/reservations/{reservation_id}/vouchers` | `ReservationVoucherController@index` | — | `voucher.manage` |
| POST | `/api/v1/staff/reservations/{reservation_id}/voucher/apply` | `ReservationVoucherController@apply` | `staff.reservation-voucher-apply` | `voucher.manage` |
| POST | `/api/v1/staff/reservations/{reservation_id}/voucher/remove` | `ReservationVoucherController@remove` | `staff.reservation-voucher-remove` | `voucher.manage` |
| POST | `/api/v1/staff/reservations/{reservation_id}/voucher/release` | `ReservationVoucherController@release` | `staff.reservation-voucher-remove` | `voucher.manage` |

---

## 6. Loyalty Endpoints

| Method | Path | Controller | Idempotency | Capability |
|---|---|---|---|---|
| GET | `/api/v1/staff/users/{user_id}/loyalty` | `LoyaltyLedgerController@showUser` | — | `loyalty.view` |
| POST | `/api/v1/staff/users/{user_id}/loyalty/adjust` | `LoyaltyLedgerController@adjustUser` | `staff.user-loyalty-adjust` | `loyalty.adjust` |
| GET | `/api/v1/staff/reservations/{reservation_id}/loyalty` | `LoyaltyLedgerController@showReservation` | — | `loyalty.view` |
| POST | `/api/v1/staff/reservations/{reservation_id}/loyalty/redeem` | `LoyaltyLedgerController@redeemReservation` | `staff.reservation-loyalty-redeem` | `loyalty.redeem` |
| POST | `/api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release` | `LoyaltyLedgerController@releaseReservation` | `staff.reservation-loyalty-release` | `loyalty.redeem` |
| POST | `/api/v1/staff/reservations/{reservation_id}/loyalty/release` | `LoyaltyLedgerController@legacyReleaseReservation` | `staff.reservation-loyalty-release` | `loyalty.redeem` |

---

## 7. Payment / Refund State Enums

### PaymentStatus (app/Enums/PaymentStatus.php)
- `Pending` — payment created, not yet captured
- `Partial` — partial payment received
- `Success` — fully paid
- `Failed` — payment failed
- `Refunded` — refunded

### ReservationOrderStatus (app/Enums/ReservationOrderStatus.php)
- `Active` — order open
- `Cancelled` — order cancelled
- `Completed` — order paid/finalized

### ReservationStatus (app/Enums/ReservationStatus.php)
- `Reserved` — checked in, dine-in active
- `Settled` — payment complete
- `Cancelled` — cancelled
- *(other states for deposit, pre-arrival, etc.)*

---

## 8. Idempotency / Replay Behavior

All mutating checkout/payment endpoints support idempotency via:
- `Idempotency-Key` header (primary) or `X-Idempotency-Key` or `idempotency_key` body field
- `OrderSettlementWorkflow` uses `CashieringReplayRecorder` (DB table) + Redis cache for payment replay
- Double payment prevented by: (1) DB unique constraint on `idempotency_key`, (2) replay cache hit, (3) order status check before mutation
- Refund also uses idempotency via the same header pattern

---

## 9. Backend Service Architecture

| Service | Responsibility |
|---|---|
| `OrderSettlementWorkflow` | Main orchestrator for checkout, bill lock, pay, refund |
| `PaymentCaptureService` | Executes locked payment capture |
| `RefundExecutionService` | Refund plan + execution |
| `RefundPlannerService` | Computes refundable amounts |
| `BillLockService` | Locks bill snapshot on reservation |
| `SettlementAmountCalculator` | Computes subtotal/tax/service_charge/discount/total |
| `StaffCashierShiftService` | Open/close/query cashier shift |
| `StaffFinancialReconciliationService` | Read model for reconciliation list/show/export |

---

## 10. Current UI Components (staff-web)

- `staff-web/src/domains/finance/` — `finance-review.ts`, `errors.ts`, related tests
- Settlement/checkout page: `/ops/orders/{id}` → "Thanh toán" button → settlement screen
- Cashier shift: `/cashier-shift` route
- Refund: No dedicated UI page found in audit (API-level only confirmed)
- Voucher/Loyalty: No dedicated UI screen confirmed on settlement screen

---

## 11. Gaps and Risks

| Gap | Severity | Notes |
|---|---|---|
| Refund UI | HIGH | API exists (`payment.refund` capability), UI locator not found in previous audit. E2E will test via API. |
| Cashier shift close UI | MEDIUM | `/cashier-shift` page exists but close button locator was unreliable in previous audit. Will test via API. |
| Voucher seed data | MEDIUM | Walk-in reservation may not have voucher applied. Require active voucher code in seed. NEEDS_DATA if absent. |
| Loyalty seed data | MEDIUM | Walk-in reservation may not have loyalty points. NEEDS_DATA if absent. |
| Refund cancel | LOW | `refund-cancel` route exists but no "pending refund" state found in audit. Will test if possible. |
| Over-refund guard | HIGH | Must verify API rejects refund > refundable amount. |
| Permission guard scope | MEDIUM | bootstrap-admin has all capabilities. Need an account with limited capability to test 403. |
| Bill snapshot API path change | LOW | `orders/{id}/close` deprecated in favor of `orders/{id}/bill-snapshot`. Both tested. |
