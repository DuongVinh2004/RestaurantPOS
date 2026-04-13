<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\IngredientStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderReconciliationService
{
    private const EPSILON = 0.0005;

    /**
     * @return array{
     *     duplicate_reference_count:int,
     *     duplicate_movement_count:int,
     *     examples:list<array{reference_id:string,duplicate_count:int,movement_ids:list<int>}>
     * }
     */
    public function duplicatePurchaseReceiptReferenceSummary(int $limit = 3): array
    {
        if (! Schema::hasTable('ingredient_stock_movements')) {
            return [
                'duplicate_reference_count' => 0,
                'duplicate_movement_count' => 0,
                'examples' => [],
            ];
        }

        $limit = max(1, min($limit, 20));

        $duplicateReferences = DB::table('ingredient_stock_movements')
            ->select('reference_id')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->where('reference_type', 'PurchaseReceipt')
            ->whereNotNull('reference_id')
            ->where('reference_id', '<>', '')
            ->groupBy('reference_id')
            ->havingRaw('COUNT(*) > 1');

        $summary = DB::query()
            ->fromSub($duplicateReferences, 'duplicate_purchase_receipt_references')
            ->selectRaw('COUNT(*) AS duplicate_reference_count')
            ->selectRaw('COALESCE(SUM(duplicate_count), 0) AS duplicate_movement_count')
            ->first();

        $examples = collect((clone $duplicateReferences)
            ->orderBy('reference_id')
            ->limit($limit)
            ->get())
            ->map(function (object $row): array {
                $referenceId = (string) ($row->reference_id ?? '');

                return [
                    'reference_id' => $referenceId,
                    'duplicate_count' => (int) ($row->duplicate_count ?? 0),
                    'movement_ids' => IngredientStockMovement::query()
                        ->where('reference_type', 'PurchaseReceipt')
                        ->where('reference_id', $referenceId)
                        ->orderBy('movement_id')
                        ->pluck('movement_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'duplicate_reference_count' => (int) ($summary->duplicate_reference_count ?? 0),
            'duplicate_movement_count' => (int) ($summary->duplicate_movement_count ?? 0),
            'examples' => $examples,
        ];
    }

    /**
     * @param  array{branch_id?:int|null,include_cancelled?:bool|null,limit?:int|null}  $filters
     * @return array{
     *     checked_order_count:int,
     *     issue_order_count:int,
     *     line_issue_count:int,
     *     receipt_issue_count:int,
     *     movement_issue_count:int,
     *     orders:list<array<string,mixed>>
     * }
     */
    public function scan(array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 50), 200));
        $query = PurchaseOrder::query()
            ->with([
                'lines.receiptLines',
                'receipts.lines.stockMovement',
            ])
            ->orderByDesc('purchase_order_id')
            ->limit($limit);

        if (($filters['branch_id'] ?? null) !== null) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! (bool) ($filters['include_cancelled'] ?? false)) {
            $query->where('purchase_order_status', '!=', PurchaseOrderStatus::Cancelled->value);
        }

        $orders = $query->get();

        $issueOrderCount = 0;
        $lineIssueCount = 0;
        $receiptIssueCount = 0;
        $movementIssueCount = 0;
        $reports = [];

        foreach ($orders as $order) {
            $report = $this->reportForOrder($order);

            if (($report['issue_count'] ?? 0) > 0) {
                $issueOrderCount++;
            }

            $lineIssueCount += (int) ($report['line_issue_count'] ?? 0);
            $receiptIssueCount += (int) ($report['receipt_issue_count'] ?? 0);
            $movementIssueCount += (int) ($report['movement_issue_count'] ?? 0);

            $reports[] = [
                'purchase_order_id' => (int) ($report['purchase_order_id'] ?? 0),
                'order_code' => (string) ($order->order_code ?? ''),
                'branch_id' => (int) ($order->branch_id ?? 0),
                'purchase_order_status' => (string) ($report['purchase_order_status'] ?? ''),
                'expected_purchase_order_status' => $report['expected_purchase_order_status'] ?? null,
                'issue_count' => (int) ($report['issue_count'] ?? 0),
                'line_issue_count' => (int) ($report['line_issue_count'] ?? 0),
                'receipt_issue_count' => (int) ($report['receipt_issue_count'] ?? 0),
                'movement_issue_count' => (int) ($report['movement_issue_count'] ?? 0),
                'issue_types' => array_values(array_unique(array_map(
                    static fn (array $issue): string => (string) ($issue['type'] ?? 'unknown'),
                    array_slice((array) ($report['issues'] ?? []), 0, 5),
                ))),
            ];
        }

        return [
            'checked_order_count' => $orders->count(),
            'issue_order_count' => $issueOrderCount,
            'line_issue_count' => $lineIssueCount,
            'receipt_issue_count' => $receiptIssueCount,
            'movement_issue_count' => $movementIssueCount,
            'orders' => $reports,
        ];
    }

    /**
     * @return array{
     *     purchase_order_id:int,
     *     purchase_order_status:string,
     *     expected_purchase_order_status:?string,
     *     issue_count:int,
     *     line_issue_count:int,
     *     receipt_issue_count:int,
     *     movement_issue_count:int,
     *     issues:list<array<string,mixed>>
     * }
     */
    public function report(int $purchaseOrderId): array
    {
        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()
            ->with([
                'lines.receiptLines',
                'receipts.lines.stockMovement',
            ])
            ->find($purchaseOrderId);

        if (! $order instanceof PurchaseOrder) {
            throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$purchaseOrderId]);
        }

        return $this->reportForOrder($order);
    }

    /**
     * @return array{
     *     purchase_order_id:int,
     *     purchase_order_status:string,
     *     expected_purchase_order_status:?string,
     *     issue_count:int,
     *     line_issue_count:int,
     *     receipt_issue_count:int,
     *     movement_issue_count:int,
     *     issues:list<array<string,mixed>>
     * }
     */
    public function reportForOrder(PurchaseOrder $order): array
    {
        $orderStatus = $order->purchase_order_status instanceof PurchaseOrderStatus
            ? $order->purchase_order_status
            : PurchaseOrderStatus::from((string) $order->purchase_order_status);

        $issues = [];
        $lineIssueCount = 0;
        $receiptIssueCount = 0;
        $movementIssueCount = 0;

        $lineMap = $order->lines->keyBy('po_line_id');
        $hasAnyReceived = false;
        $isFullyReceived = $order->lines->isNotEmpty();

        foreach ($order->lines as $line) {
            $receiptTotal = (float) $line->receiptLines->sum(static fn (PurchaseReceiptLine $receiptLine): float => (float) $receiptLine->received_quantity);
            $recordedReceived = (float) $line->received_quantity;
            $orderedQuantity = (float) $line->ordered_quantity;

            if (abs($recordedReceived - $receiptTotal) > self::EPSILON) {
                $lineIssueCount++;
                $issues[] = [
                    'type' => 'line_received_quantity_mismatch',
                    'po_line_id' => (int) $line->po_line_id,
                    'message' => sprintf(
                        'Purchase order line [%d] stores received_quantity [%s] but receipt lines total [%s].',
                        (int) $line->po_line_id,
                        number_format($recordedReceived, 3, '.', ''),
                        number_format($receiptTotal, 3, '.', '')
                    ),
                ];
            }

            if (($receiptTotal - $orderedQuantity) > self::EPSILON) {
                $lineIssueCount++;
                $issues[] = [
                    'type' => 'line_over_received',
                    'po_line_id' => (int) $line->po_line_id,
                    'message' => sprintf(
                        'Purchase order line [%d] exceeds ordered quantity [%s] with receipt total [%s].',
                        (int) $line->po_line_id,
                        number_format($orderedQuantity, 3, '.', ''),
                        number_format($receiptTotal, 3, '.', '')
                    ),
                ];
            }

            if ($receiptTotal > self::EPSILON) {
                $hasAnyReceived = true;
            }

            if (($orderedQuantity - $receiptTotal) > self::EPSILON) {
                $isFullyReceived = false;
            }
        }

        foreach ($order->receipts as $receipt) {
            $this->collectReceiptLineIssues($order, $receipt, $lineMap->all(), $issues, $receiptIssueCount, $movementIssueCount);
        }

        $expectedStatus = null;
        if ($hasAnyReceived) {
            $expectedStatus = $isFullyReceived
                ? PurchaseOrderStatus::Received
                : PurchaseOrderStatus::PartiallyReceived;
        } elseif ($order->receipts->isNotEmpty()) {
            $expectedStatus = PurchaseOrderStatus::Ordered;
        }

        if ($expectedStatus !== null && $orderStatus !== $expectedStatus) {
            $receiptIssueCount++;
            $issues[] = [
                'type' => 'purchase_order_status_mismatch',
                'message' => sprintf(
                    'Purchase order status [%s] does not match the receiving ledger state [%s].',
                    $orderStatus->value,
                    $expectedStatus->value
                ),
            ];
        }

        if ($expectedStatus === PurchaseOrderStatus::Received && $order->received_at === null) {
            $receiptIssueCount++;
            $issues[] = [
                'type' => 'purchase_order_received_at_missing',
                'message' => 'Purchase order is fully received but received_at is not set.',
            ];
        }

        if ($expectedStatus !== PurchaseOrderStatus::Received && $order->received_at !== null && $orderStatus !== PurchaseOrderStatus::Cancelled) {
            $receiptIssueCount++;
            $issues[] = [
                'type' => 'purchase_order_received_at_unexpected',
                'message' => 'Purchase order received_at is set before all line quantities are fully received.',
            ];
        }

        return [
            'purchase_order_id' => (int) $order->purchase_order_id,
            'purchase_order_status' => $orderStatus->value,
            'expected_purchase_order_status' => $expectedStatus?->value,
            'issue_count' => count($issues),
            'line_issue_count' => $lineIssueCount,
            'receipt_issue_count' => $receiptIssueCount,
            'movement_issue_count' => $movementIssueCount,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<int,PurchaseOrderLine>  $lineMap
     * @param  list<array<string,mixed>>  $issues
     */
    private function collectReceiptLineIssues(
        PurchaseOrder $order,
        PurchaseReceipt $receipt,
        array $lineMap,
        array &$issues,
        int &$receiptIssueCount,
        int &$movementIssueCount,
    ): void {
        foreach ($receipt->lines as $receiptLine) {
            $orderLine = $lineMap[(int) $receiptLine->purchase_order_line_id] ?? null;
            if (! $orderLine instanceof PurchaseOrderLine) {
                $receiptIssueCount++;
                $issues[] = [
                    'type' => 'receipt_line_order_line_missing',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'message' => sprintf(
                        'Receipt line [%d] references missing purchase order line [%d].',
                        (int) $receiptLine->receipt_line_id,
                        (int) $receiptLine->purchase_order_line_id
                    ),
                ];

                continue;
            }

            $expectedReferenceId = (string) $receipt->receipt_code.':'.(int) $receiptLine->purchase_order_line_id;
            $stockMovement = $receiptLine->relationLoaded('stockMovement') ? $receiptLine->stockMovement : null;

            if (! $stockMovement instanceof IngredientStockMovement) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'receipt_line_stock_movement_missing',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'message' => sprintf('Receipt line [%d] is missing its stock movement lineage.', (int) $receiptLine->receipt_line_id),
                ];

                continue;
            }

            if ((int) $stockMovement->ingredient_id !== (int) $receiptLine->ingredient_id) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_ingredient_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] ingredient does not match receipt line [%d].', (int) $stockMovement->movement_id, (int) $receiptLine->receipt_line_id),
                ];
            }

            if ((int) $stockMovement->branch_id !== (int) $order->branch_id) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_branch_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] branch does not match purchase order branch.', (int) $stockMovement->movement_id),
                ];
            }

            if ((string) $stockMovement->movement_type !== IngredientStockMovement::TYPE_STOCK_IN) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_type_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] must be StockIn for a purchase receipt line.', (int) $stockMovement->movement_id),
                ];
            }

            if ((string) $stockMovement->reference_type !== 'PurchaseReceipt') {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_reference_type_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] must use PurchaseReceipt reference_type.', (int) $stockMovement->movement_id),
                ];
            }

            if ((string) $stockMovement->reference_id !== $expectedReferenceId) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_reference_id_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] reference_id drifted from expected receipt lineage [%s].', (int) $stockMovement->movement_id, $expectedReferenceId),
                ];
            }

            $duplicateReferenceCount = IngredientStockMovement::query()
                ->where('ingredient_id', (int) $receiptLine->ingredient_id)
                ->where('reference_type', 'PurchaseReceipt')
                ->where('reference_id', $expectedReferenceId)
                ->count();

            if ($duplicateReferenceCount > 1) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_reference_duplicate',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'message' => sprintf(
                        'Receipt lineage [%s] is duplicated across %d stock movements.',
                        $expectedReferenceId,
                        $duplicateReferenceCount
                    ),
                ];
            }

            if (abs((float) $stockMovement->quantity_delta - (float) $receiptLine->received_quantity) > self::EPSILON) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_quantity_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf(
                        'Stock movement [%d] quantity_delta [%s] does not match receipt line [%d] received_quantity [%s].',
                        (int) $stockMovement->movement_id,
                        number_format((float) $stockMovement->quantity_delta, 3, '.', ''),
                        (int) $receiptLine->receipt_line_id,
                        number_format((float) $receiptLine->received_quantity, 3, '.', '')
                    ),
                ];
            }

            if (strtolower(trim((string) $stockMovement->unit_code)) !== strtolower(trim((string) $receiptLine->unit_code))) {
                $movementIssueCount++;
                $issues[] = [
                    'type' => 'stock_movement_unit_mismatch',
                    'receipt_line_id' => (int) $receiptLine->receipt_line_id,
                    'stock_movement_id' => (int) $stockMovement->movement_id,
                    'message' => sprintf('Stock movement [%d] unit_code does not match receipt line [%d].', (int) $stockMovement->movement_id, (int) $receiptLine->receipt_line_id),
                ];
            }
        }
    }
}
