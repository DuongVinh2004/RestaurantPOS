# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md` (Task C)
- `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`

## Code hotspots

- `app/Http/Controllers/Api/Staff/StaffCheckoutController.php`
- `app/Http/Controllers/Api/Staff/StaffCashierShiftController.php`
- `app/Http/Controllers/Api/Staff/StaffFinanceInvoiceController.php`
- `app/Http/Controllers/Api/Staff/StaffFinancialReconciliationController.php`
- `app/Services/Staff/StaffCheckoutService.php`
- `app/Services/Staff/BillLockService.php`
- `app/Services/Staff/PaymentCaptureService.php`
- `app/Services/Staff/RefundExecutionService.php`
- `app/Services/Staff/RefundPlannerService.php`
- `app/Services/Staff/SettlementAmountCalculator.php`
- `app/Services/Staff/SettlementFinalizerService.php`
- `app/Services/Staff/StaffCashierShiftService.php`
- `app/Services/Staff/StaffInvoiceService.php`
- `app/Services/Staff/StaffFinancialReconciliationService.php`
- `app/Services/PaymentIntegration/*`
- `app/Services/ReservationFinancialSyncService.php`

## Test surface

- `tests/Feature/Staff/StaffCheckout*.php`
- `tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php`
- `tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php`
- `tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php`
- `tests/Feature/Payments/*`
- `tests/Feature/Financial/ReservationPaymentIntegrityFlowTest.php`
- `tests/Unit/Services/Staff/*`
- `tests/Unit/Services/PaymentIntegration/*`
- `tests/Unit/Support/RefundAllocationPolicyTest.php`
- `tests/Unit/Support/PaymentIntegrityGuardTest.php`

## Questions to answer before patching

- Where is the duplicate or replay guard enforced today?
- Which write must be atomic with settlement finalization?
- What lineage proves a refund is legal and reversible?
- Which provider or webhook ordering assumptions would break if this change lands?
