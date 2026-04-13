# Inventory Purchasing Lifecycle

This runbook documents the current purchase order and receipt contract for the `inventory.uplift` admin surface.

## Purchase Order Lifecycle

- `Draft`
  - editable
  - cannot receive stock
- `Ordered`
  - canonical open state for receiving
  - first receipt moves the order to `PartiallyReceived` or `Received`
- `PartiallyReceived`
  - additional receipts allowed while remaining quantity exists
  - branch, supplier, line replacement, and manual status override stay blocked
- `Received`
  - terminal receiving state
  - no additional receipts allowed
- `Cancelled`
  - terminal non-receiving state
  - cannot be created directly by the API once receipt history exists

## Receipt Posting Invariants

- every receipt line must belong to the target purchase order
- every receipt line must use the purchase-order-line unit
- over-receive is blocked against the remaining quantity window on each line
- receipt posting is append-only: stock-affecting receipt history is captured in `purchase_receipts`, `purchase_receipt_lines`, and `ingredient_stock_movements`
- every receipt line must point to a `PurchaseReceipt` stock movement via `stock_movement_id`
- stock movement reference lineage is `receipt_code:purchase_order_line_id`

## Replay and Duplicate Protection

- same idempotency key still replays through the idempotency middleware
- identical reposts of the same `supplier_document_no` on the same purchase order now replay the existing receipt even with a different idempotency key
- if the same `supplier_document_no` is reposted with different receipt lines, the request is rejected
- requests that omit both a stable idempotency key and a stable supplier document number still rely on remaining-quantity guards rather than full intent dedupe

## Reconciliation

Use the reconciliation service when PO line totals, receipt lines, or stock movement lineage look suspicious:

```php
app(App\Services\Inventory\PurchaseOrderReconciliationService::class)->report($purchaseOrderId);
```

The report checks:

- purchase-order-line `received_quantity` vs summed receipt lines
- cumulative over-receive drift
- purchase order status vs actual receiving completion
- missing stock movement lineage
- duplicate `PurchaseReceipt` stock movement references
- branch, ingredient, unit, quantity, and reference-id mismatches between receipt lines and stock movements

For a broader operator view across recent purchasing records, the shared ops snapshot now also exposes:

- `inventory_purchasing.checked_order_count`
- `inventory_purchasing.issue_order_count`
- `inventory_purchasing.line_issue_count`
- `inventory_purchasing.receipt_issue_count`
- `inventory_purchasing.movement_issue_count`
- `inventory_purchasing.duplicate_purchase_receipt_reference_count`
- `inventory_purchasing.duplicate_purchase_receipt_movement_count`
- `inventory_purchasing.overdue_open_order_count`
- `inventory_purchasing.oldest_overdue_open_age_seconds`
- `inventory_purchasing.issue_examples[]`
- `inventory_purchasing.overdue_examples[]`
- `inventory_purchasing.duplicate_purchase_receipt_reference_examples[]`

Use the release-grade gate when staging or deploy evidence must include inventory confidence:

```powershell
php artisan booking:ops-snapshot --json
php artisan booking:deploy-check --mode=preflight --strict
```

Interpretation:

- `status=fail`
  - receipt or stock movement lineage has already drifted; do not trust stock-affecting reporting or purchasing evidence until reconciled
  - duplicate `PurchaseReceipt` references are now treated as a release blocker because `database/patches/2026_04_13_000051_inventory_stock_movement_reference_uniqueness.sql` will stop when they exist
- `status=degraded`
  - open purchase orders are aging past the configured window but ledger invariants still hold

Expected operator action:

- inspect `issue_examples[]` first; a movement-lineage issue is more urgent than an overdue PO
- if `duplicate_purchase_receipt_reference_count > 0`, clean up the listed `duplicate_purchase_receipt_reference_examples[]` before rerunning deploy preflight
- run the per-order reconciliation service for the flagged order ids before collecting release evidence
- if only overdue orders remain, confirm whether the backlog is operationally expected and capture that note with the rollout evidence

## Verification

Run the inventory and purchasing regression slice after receiving changes:

```powershell
php artisan test tests/Feature/Admin/AdminInventoryFoundationHttpFlowTest.php tests/Feature/Admin/AdminPurchasingFoundationHttpFlowTest.php tests/Feature/Admin/AdminInventoryKitchenPurchasing*.php
```

If receipt lineage or stock movement math changed, also run:

```powershell
php artisan test tests/Feature/Admin/AdminReportingReadModelsFoundationHttpFlowTest.php --filter=inventory
```
