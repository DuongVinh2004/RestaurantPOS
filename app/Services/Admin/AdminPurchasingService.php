<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Models\Ingredient;
use App\Models\IngredientStockMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\Supplier;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Services\Inventory\InventoryStockMovementService;
use App\Services\Inventory\PurchaseOrderReconciliationService;
use App\Support\AuditEvent;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminPurchasingService
{
    public function __construct(
        private readonly InventoryStockMovementService $stockMovementService,
        private readonly BranchContextService $branchContextService,
        private readonly PurchaseOrderReconciliationService $purchaseOrderReconciliationService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginateSuppliers(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.admin_inventory_page_default', 25)), (int) config('booking.admin_inventory_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $keyword = trim((string) ($filters['q'] ?? ''));
        [$sortColumn, $sortDirection] = $this->resolveSupplierSort(
            (string) ($filters['sort_by'] ?? 'name'),
            (string) ($filters['sort_dir'] ?? 'asc'),
        );

        return Supplier::query()
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, static fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($keyword !== '', static function ($query) use ($keyword): void {
                $like = SafeLike::contains($keyword);
                $query->where(function ($inner) use ($like): void {
                    $inner
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'supplier_id', static fn ($query) => $query->orderBy('supplier_id'))
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends([
                'is_active' => array_key_exists('is_active', $filters) && $filters['is_active'] !== null ? (bool) $filters['is_active'] : null,
                'q' => $keyword !== '' ? $keyword : null,
                'sort' => $filters['sort'] ?? null,
            ]);
    }

    public function findSupplier(int $supplierId): Supplier
    {
        /** @var Supplier|null $supplier */
        $supplier = Supplier::query()->find($supplierId);

        if (! $supplier instanceof Supplier) {
            throw (new ModelNotFoundException)->setModel(Supplier::class, [$supplierId]);
        }

        return $supplier;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createSupplier(array $payload): Supplier
    {
        $supplier = new Supplier;
        $supplier->fill([
            'code' => $this->normalizeNullableString($payload['code'] ?? null),
            'name' => trim((string) $payload['name']),
            'contact_name' => $this->normalizeNullableString($payload['contact_name'] ?? null),
            'phone' => $this->normalizeNullableString($payload['phone'] ?? null),
            'email' => $this->normalizeNullableString($payload['email'] ?? null),
            'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
        ]);
        $supplier->save();

        AuditEvent::info('admin.supplier.created', [
            'supplier_id' => (int) $supplier->supplier_id,
            'code' => $supplier->code,
        ]);

        return $this->findSupplier((int) $supplier->supplier_id);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateSupplier(int $supplierId, array $payload): Supplier
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->findOrFail($supplierId);

        if (array_key_exists('code', $payload)) {
            $supplier->code = $this->normalizeNullableString($payload['code']);
        }
        if (array_key_exists('name', $payload)) {
            $supplier->name = trim((string) $payload['name']);
        }
        if (array_key_exists('contact_name', $payload)) {
            $supplier->contact_name = $this->normalizeNullableString($payload['contact_name']);
        }
        if (array_key_exists('phone', $payload)) {
            $supplier->phone = $this->normalizeNullableString($payload['phone']);
        }
        if (array_key_exists('email', $payload)) {
            $supplier->email = $this->normalizeNullableString($payload['email']);
        }
        if (array_key_exists('notes', $payload)) {
            $supplier->notes = $this->normalizeNullableString($payload['notes']);
        }
        if (array_key_exists('is_active', $payload)) {
            $supplier->is_active = (bool) $payload['is_active'];
        }

        $supplier->save();

        AuditEvent::info('admin.supplier.updated', [
            'supplier_id' => $supplierId,
        ]);

        return $this->findSupplier($supplierId);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginatePurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.admin_inventory_page_default', 25)), (int) config('booking.admin_inventory_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $keyword = trim((string) ($filters['q'] ?? ''));
        [$sortColumn, $sortDirection] = $this->resolvePurchaseOrderSort(
            (string) ($filters['sort_by'] ?? 'created_at'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        return $this->basePurchaseOrdersQuery()
            ->when(isset($filters['supplier_id']), static fn ($query) => $query->where('purchase_orders.supplier_id', (int) $filters['supplier_id']))
            ->when(isset($filters['purchase_order_status']), static fn ($query) => $query->where('purchase_orders.purchase_order_status', (string) $filters['purchase_order_status']))
            ->when(isset($filters['branch_id']), static fn ($query) => $query->where('purchase_orders.branch_id', (int) $filters['branch_id']))
            ->when($keyword !== '', static function ($query) use ($keyword): void {
                $like = SafeLike::contains($keyword);
                $query->where(function ($inner) use ($like): void {
                    $inner
                        ->where('purchase_orders.order_code', 'like', $like)
                        ->orWhere('purchase_orders.supplier_reference', 'like', $like)
                        ->orWhere('purchase_orders.notes', 'like', $like);
                });
            })
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'purchase_orders.purchase_order_id', static fn ($query) => $query->orderByDesc('purchase_orders.purchase_order_id'))
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends([
                'supplier_id' => $filters['supplier_id'] ?? null,
                'purchase_order_status' => $filters['purchase_order_status'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'q' => $keyword !== '' ? $keyword : null,
                'sort' => $filters['sort'] ?? null,
            ]);
    }

    public function findPurchaseOrder(int $purchaseOrderId): PurchaseOrder
    {
        /** @var PurchaseOrder|null $order */
        $order = $this->basePurchaseOrdersQuery()
            ->with([
                'supplier' => static fn ($query) => $query->select('supplier_id', 'code', 'name', 'contact_name', 'phone', 'email', 'is_active'),
                'branch' => static fn ($query) => $query->select('branch_id', 'branch_code', 'branch_name', 'is_default'),
                'lines' => static function ($query): void {
                    $query
                        ->with(['ingredient' => static fn ($inner) => $inner->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
                        ->orderBy('sort_order')
                        ->orderBy('po_line_id');
                },
                'receipts' => static function ($query): void {
                    $query
                        ->with([
                            'lines' => static function ($lineQuery): void {
                                $lineQuery
                                    ->with(['ingredient' => static fn ($inner) => $inner->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
                                    ->orderBy('receipt_line_id');
                            },
                        ])
                        ->orderByDesc('received_at')
                        ->orderByDesc('receipt_id');
                },
            ])
            ->find($purchaseOrderId);

        if (! $order instanceof PurchaseOrder) {
            throw (new ModelNotFoundException)->setModel(PurchaseOrder::class, [$purchaseOrderId]);
        }

        return $order;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createPurchaseOrder(array $payload, ?int $actorUserId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($payload, $actorUserId): PurchaseOrder {
            $supplierId = (int) $payload['supplier_id'];
            Supplier::query()->lockForUpdate()->findOrFail($supplierId);

            /** @var list<array<string,mixed>> $lines */
            $lines = array_values((array) $payload['lines']);
            $this->assertDistinctIngredientLines($lines);
            $ingredients = $this->loadIngredientsForLines($lines);

            $status = $this->normalizeWritableStatus((string) ($payload['purchase_order_status'] ?? PurchaseOrderStatus::Ordered->value), false);
            $orderedAt = $payload['ordered_at'] ?? null;

            $order = new PurchaseOrder;
            $order->fill([
                'branch_id' => $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null),
                'supplier_id' => $supplierId,
                'order_code' => $this->normalizeNullableString($payload['order_code'] ?? null) ?? $this->generatePurchaseOrderCode(),
                'purchase_order_status' => $status,
                'ordered_at' => $status === PurchaseOrderStatus::Draft ? $orderedAt : ($orderedAt ?? Carbon::now('UTC')),
                'expected_at' => $payload['expected_at'] ?? null,
                'received_at' => null,
                'supplier_reference' => $this->normalizeNullableString($payload['supplier_reference'] ?? null),
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
            $order->save();

            $this->replacePurchaseOrderLines($order, $lines, $ingredients);

            AuditEvent::info('admin.purchase_order.created', [
                'purchase_order_id' => (int) $order->purchase_order_id,
                'branch_id' => $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null),
                'supplier_id' => $supplierId,
                'status' => $status->value,
            ]);

            return $this->findPurchaseOrder((int) $order->purchase_order_id);
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updatePurchaseOrder(int $purchaseOrderId, array $payload, ?int $actorUserId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId, $payload, $actorUserId): PurchaseOrder {
            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);
            $receiptCount = PurchaseReceipt::query()->where('purchase_order_id', $purchaseOrderId)->count();

            if (array_key_exists('branch_id', $payload)) {
                if ($receiptCount > 0) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Cannot change branch after receiving has started.',
                    ]);
                }

                $order->branch_id = $this->branchContextService->resolveBranchId($payload['branch_id']);
            }

            if (array_key_exists('supplier_id', $payload)) {
                if ($receiptCount > 0) {
                    throw ValidationException::withMessages([
                        'supplier_id' => 'Cannot change supplier after receiving has started.',
                    ]);
                }

                Supplier::query()->lockForUpdate()->findOrFail((int) $payload['supplier_id']);
                $order->supplier_id = (int) $payload['supplier_id'];
            }

            if (array_key_exists('order_code', $payload)) {
                $order->order_code = $this->normalizeNullableString($payload['order_code']) ?? $order->order_code;
            }

            if (array_key_exists('purchase_order_status', $payload)) {
                if ($receiptCount > 0) {
                    throw ValidationException::withMessages([
                        'purchase_order_status' => 'Purchase order status is managed by the receiving lifecycle once receipts exist.',
                    ]);
                }

                $status = $this->normalizeWritableStatus((string) $payload['purchase_order_status'], true);
                if ($status === PurchaseOrderStatus::Cancelled && $receiptCount > 0) {
                    throw ValidationException::withMessages([
                        'purchase_order_status' => 'Cannot cancel a purchase order that already has receipts.',
                    ]);
                }

                $order->purchase_order_status = $status;
                if ($status === PurchaseOrderStatus::Ordered && $order->ordered_at === null) {
                    $order->ordered_at = Carbon::now('UTC');
                }
            }

            if (array_key_exists('ordered_at', $payload)) {
                $order->ordered_at = $payload['ordered_at'];
            }
            if (array_key_exists('expected_at', $payload)) {
                $order->expected_at = $payload['expected_at'];
            }
            if (array_key_exists('supplier_reference', $payload)) {
                $order->supplier_reference = $this->normalizeNullableString($payload['supplier_reference']);
            }
            if (array_key_exists('notes', $payload)) {
                $order->notes = $this->normalizeNullableString($payload['notes']);
            }

            if (array_key_exists('lines', $payload)) {
                if ($receiptCount > 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Cannot replace purchase order lines after receiving has started.',
                    ]);
                }

                /** @var list<array<string,mixed>> $lines */
                $lines = array_values((array) $payload['lines']);
                $this->assertDistinctIngredientLines($lines);
                $ingredients = $this->loadIngredientsForLines($lines);
                $this->replacePurchaseOrderLines($order, $lines, $ingredients);
            }

            $order->updated_by = $actorUserId;
            $order->save();

            AuditEvent::info('admin.purchase_order.updated', [
                'purchase_order_id' => $purchaseOrderId,
                'receipt_count' => $receiptCount,
            ]);

            return $this->findPurchaseOrder($purchaseOrderId);
        }, 3);
    }

    /**
     * @return array{order: PurchaseOrder, receipts: Collection<int, PurchaseReceipt>}
     */
    public function listPurchaseOrderReceipts(int $purchaseOrderId): array
    {
        $order = $this->findPurchaseOrder($purchaseOrderId);

        /** @var Collection<int, PurchaseReceipt> $receipts */
        $receipts = PurchaseReceipt::query()
            ->with([
                'lines' => static function ($query): void {
                    $query
                        ->with(['ingredient' => static fn ($inner) => $inner->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
                        ->orderBy('receipt_line_id');
                },
            ])
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderByDesc('received_at')
            ->orderByDesc('receipt_id')
            ->get();

        return [
            'order' => $order,
            'receipts' => $receipts,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{order: PurchaseOrder, receipt: PurchaseReceipt}
     */
    public function createReceipt(int $purchaseOrderId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($purchaseOrderId, $payload, $actorUserId): array {
            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);
            $status = $order->purchase_order_status instanceof PurchaseOrderStatus
                ? $order->purchase_order_status
                : PurchaseOrderStatus::from((string) $order->purchase_order_status);

            /** @var Collection<int, PurchaseOrderLine> $orderLines */
            $orderLines = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrderId)
                ->lockForUpdate()
                ->get()
                ->keyBy('po_line_id');

            if ($orderLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Purchase order has no lines to receive.',
                ]);
            }

            /** @var list<array<string,mixed>> $receiptLines */
            $receiptLines = array_values((array) $payload['lines']);
            $this->assertDistinctReceiptLines($receiptLines);
            $supplierDocumentNo = $this->normalizeNullableString($payload['supplier_document_no'] ?? null);
            $requestedReceiptSignature = $this->normalizeRequestedReceiptSignature($receiptLines, $orderLines);
            $replayedReceipt = $this->findReplayableReceipt(
                purchaseOrderId: $purchaseOrderId,
                supplierDocumentNo: $supplierDocumentNo,
                requestedReceiptSignature: $requestedReceiptSignature,
            );

            if ($replayedReceipt instanceof PurchaseReceipt) {
                AuditEvent::info('admin.purchase_order.receipt_replayed', [
                    'purchase_order_id' => $purchaseOrderId,
                    'receipt_id' => (int) $replayedReceipt->receipt_id,
                    'supplier_document_no' => $supplierDocumentNo,
                    'line_count' => count($receiptLines),
                ]);

                return [
                    'order' => $this->findPurchaseOrder($purchaseOrderId),
                    'receipt' => $replayedReceipt,
                ];
            }

            if ($status === PurchaseOrderStatus::Cancelled || $status === PurchaseOrderStatus::Received) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'This purchase order cannot receive additional stock.',
                ]);
            }

            if (! in_array($status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_status' => 'Only ordered purchase orders can receive stock.',
                ]);
            }

            $receivedAt = $payload['received_at'] ?? Carbon::now('UTC');
            $receipt = new PurchaseReceipt;
            $receipt->fill([
                'branch_id' => (int) $order->branch_id,
                'purchase_order_id' => $purchaseOrderId,
                'receipt_code' => $this->normalizeNullableString($payload['receipt_code'] ?? null) ?? $this->generateReceiptCode(),
                'receipt_status' => PurchaseReceiptStatus::Posted,
                'received_at' => $receivedAt,
                'supplier_document_no' => $supplierDocumentNo,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'created_by' => $actorUserId,
                'created_at' => Carbon::now('UTC'),
            ]);
            $receipt->save();

            foreach ($receiptLines as $index => $linePayload) {
                $poLineId = (int) $linePayload['purchase_order_line_id'];
                /** @var PurchaseOrderLine|null $orderLine */
                $orderLine = $orderLines->get($poLineId);

                if (! $orderLine instanceof PurchaseOrderLine) {
                    throw ValidationException::withMessages([
                        'lines.'.$index.'.purchase_order_line_id' => 'Purchase order line does not belong to this purchase order.',
                    ]);
                }

                $receivedQuantity = (float) $linePayload['received_quantity'];
                $remainingQuantity = (float) $orderLine->ordered_quantity - (float) $orderLine->received_quantity;

                if ($receivedQuantity - $remainingQuantity > 0.0005) {
                    throw ValidationException::withMessages([
                        'lines.'.$index.'.received_quantity' => 'Received quantity exceeds the remaining purchase order quantity.',
                    ]);
                }

                $unitCode = $this->resolvePurchaseOrderLineUnitCode(
                    $orderLine,
                    $linePayload['unit_code'] ?? null,
                    'lines.'.$index.'.unit_code'
                );
                $referenceId = $receipt->receipt_code.':'.$orderLine->po_line_id;
                $movement = $this->stockMovementService->recordMovement((int) $orderLine->ingredient_id, [
                    'branch_id' => (int) $order->branch_id,
                    'movement_type' => IngredientStockMovement::TYPE_STOCK_IN,
                    'quantity' => $receivedQuantity,
                    'unit_code' => $unitCode,
                    'reference_type' => 'PurchaseReceipt',
                    'reference_id' => $referenceId,
                    'notes' => $linePayload['notes'] ?? $payload['notes'] ?? sprintf('Purchase receipt %s', $receipt->receipt_code),
                    'created_at' => $receivedAt,
                ], $actorUserId);

                PurchaseReceiptLine::query()->create([
                    'receipt_id' => (int) $receipt->receipt_id,
                    'purchase_order_line_id' => (int) $orderLine->po_line_id,
                    'ingredient_id' => (int) $orderLine->ingredient_id,
                    'received_quantity' => number_format($receivedQuantity, 3, '.', ''),
                    'unit_code' => $unitCode,
                    'unit_cost' => array_key_exists('unit_cost', $linePayload) && $linePayload['unit_cost'] !== null
                        ? number_format((float) $linePayload['unit_cost'], 3, '.', '')
                        : null,
                    'stock_movement_id' => (int) $movement->movement_id,
                    'notes' => $this->normalizeNullableString($linePayload['notes'] ?? null),
                    'created_at' => $receivedAt,
                ]);

                $orderLine->received_quantity = number_format(((float) $orderLine->received_quantity) + $receivedQuantity, 3, '.', '');
                if (array_key_exists('unit_cost', $linePayload) && $linePayload['unit_cost'] !== null) {
                    $orderLine->unit_cost = number_format((float) $linePayload['unit_cost'], 3, '.', '');
                }
                $orderLine->save();
            }

            $freshLines = PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrderId)->get();
            $hasAnyReceived = $freshLines->contains(static fn (PurchaseOrderLine $line): bool => (float) $line->received_quantity > 0.0005);
            $isFullyReceived = $freshLines->every(static fn (PurchaseOrderLine $line): bool => ((float) $line->ordered_quantity - (float) $line->received_quantity) <= 0.0005);

            $order->purchase_order_status = $isFullyReceived
                ? PurchaseOrderStatus::Received
                : ($hasAnyReceived ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Ordered);
            $order->ordered_at = $order->ordered_at ?? Carbon::now('UTC');
            $order->received_at = $isFullyReceived ? $receivedAt : null;
            $order->updated_by = $actorUserId;
            $order->save();

            $reconciliation = $this->purchaseOrderReconciliationService->report($purchaseOrderId);
            if ((int) ($reconciliation['issue_count'] ?? 0) > 0) {
                AuditEvent::warning('admin.purchase_order.receipt_reconciliation_drift_detected', [
                    'purchase_order_id' => $purchaseOrderId,
                    'receipt_id' => (int) $receipt->receipt_id,
                    'issue_count' => (int) ($reconciliation['issue_count'] ?? 0),
                    'line_issue_count' => (int) ($reconciliation['line_issue_count'] ?? 0),
                    'receipt_issue_count' => (int) ($reconciliation['receipt_issue_count'] ?? 0),
                    'movement_issue_count' => (int) ($reconciliation['movement_issue_count'] ?? 0),
                ]);
            }

            AuditEvent::info('admin.purchase_order.receipt_posted', [
                'purchase_order_id' => $purchaseOrderId,
                'receipt_id' => (int) $receipt->receipt_id,
                'line_count' => count($receiptLines),
            ]);

            return [
                'order' => $this->findPurchaseOrder($purchaseOrderId),
                'receipt' => PurchaseReceipt::query()
                    ->with([
                        'lines' => static function ($query): void {
                            $query
                                ->with(['ingredient' => static fn ($inner) => $inner->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
                                ->orderBy('receipt_line_id');
                        },
                    ])
                    ->findOrFail((int) $receipt->receipt_id),
            ];
        }, 3);
    }

    private function basePurchaseOrdersQuery()
    {
        return PurchaseOrder::query()
            ->with([
                'supplier' => static fn ($query) => $query->select('supplier_id', 'code', 'name', 'contact_name', 'phone', 'email', 'is_active'),
                'branch' => static fn ($query) => $query->select('branch_id', 'branch_code', 'branch_name', 'is_default'),
            ])
            ->withCount([
                'lines as line_count',
                'receipts as receipt_count',
            ])
            ->withSum('lines as ordered_total_quantity', 'ordered_quantity')
            ->withSum('lines as received_total_quantity', 'received_quantity');
    }

    /**
     * @param  list<array<string,mixed>>  $lines
     * @return array<int, Ingredient>
     */
    private function loadIngredientsForLines(array $lines): array
    {
        $ingredientIds = collect($lines)
            ->pluck('ingredient_id')
            ->map(static fn ($value): int => (int) $value)
            ->values()
            ->all();

        $ingredients = Ingredient::query()
            ->whereIn('ingredient_id', $ingredientIds)
            ->get()
            ->keyBy('ingredient_id')
            ->all();

        if (count(array_unique($ingredientIds)) !== count($ingredients)) {
            throw (new ModelNotFoundException)->setModel(Ingredient::class, $ingredientIds);
        }

        return $ingredients;
    }

    /**
     * @param  list<array<string,mixed>>  $lines
     */
    private function assertDistinctIngredientLines(array $lines): void
    {
        $ingredientIds = collect($lines)
            ->pluck('ingredient_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        if (count($ingredientIds) !== count(array_unique($ingredientIds))) {
            throw ValidationException::withMessages([
                'lines' => 'Each ingredient may appear only once per purchase order.',
            ]);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $receiptLines
     */
    private function assertDistinctReceiptLines(array $receiptLines): void
    {
        $poLineIds = collect($receiptLines)
            ->pluck('purchase_order_line_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        if (count($poLineIds) !== count(array_unique($poLineIds))) {
            throw ValidationException::withMessages([
                'lines' => 'Each purchase order line may appear only once per receipt.',
            ]);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $receiptLines
     * @param  Collection<int, PurchaseOrderLine>  $orderLines
     * @return list<string>
     */
    private function normalizeRequestedReceiptSignature(array $receiptLines, Collection $orderLines): array
    {
        $signature = [];

        foreach ($receiptLines as $index => $linePayload) {
            $poLineId = (int) $linePayload['purchase_order_line_id'];
            /** @var PurchaseOrderLine|null $orderLine */
            $orderLine = $orderLines->get($poLineId);
            if (! $orderLine instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.purchase_order_line_id' => 'Purchase order line does not belong to this purchase order.',
                ]);
            }

            $signature[] = $this->receiptLineSignature(
                purchaseOrderLineId: $poLineId,
                receivedQuantity: (float) $linePayload['received_quantity'],
                unitCode: $this->resolvePurchaseOrderLineUnitCode(
                    $orderLine,
                    $linePayload['unit_code'] ?? null,
                    'lines.'.$index.'.unit_code'
                ),
                unitCost: array_key_exists('unit_cost', $linePayload) && $linePayload['unit_cost'] !== null
                    ? (float) $linePayload['unit_cost']
                    : null,
            );
        }

        sort($signature);

        return $signature;
    }

    /**
     * @param  list<string>  $requestedReceiptSignature
     */
    private function findReplayableReceipt(
        int $purchaseOrderId,
        ?string $supplierDocumentNo,
        array $requestedReceiptSignature,
    ): ?PurchaseReceipt {
        if ($supplierDocumentNo === null) {
            return null;
        }

        /** @var Collection<int, PurchaseReceipt> $candidateReceipts */
        $candidateReceipts = PurchaseReceipt::query()
            ->with([
                'lines' => static function ($query): void {
                    $query
                        ->with(['ingredient' => static fn ($inner) => $inner->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
                        ->orderBy('receipt_line_id');
                },
            ])
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('supplier_document_no', $supplierDocumentNo)
            ->orderByDesc('receipt_id')
            ->get();

        foreach ($candidateReceipts as $candidateReceipt) {
            $existingSignature = $candidateReceipt->lines
                ->map(fn (PurchaseReceiptLine $line): string => $this->receiptLineSignature(
                    purchaseOrderLineId: (int) $line->purchase_order_line_id,
                    receivedQuantity: (float) $line->received_quantity,
                    unitCode: (string) $line->unit_code,
                    unitCost: $line->unit_cost !== null ? (float) $line->unit_cost : null,
                ))
                ->sort()
                ->values()
                ->all();

            if ($existingSignature === $requestedReceiptSignature) {
                return $candidateReceipt;
            }
        }

        if ($candidateReceipts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supplier_document_no' => sprintf(
                    'Supplier document [%s] is already posted for this purchase order with different receipt details.',
                    $supplierDocumentNo
                ),
            ]);
        }

        return null;
    }

    private function receiptLineSignature(
        int $purchaseOrderLineId,
        float $receivedQuantity,
        string $unitCode,
        ?float $unitCost,
    ): string {
        return implode('|', [
            $purchaseOrderLineId,
            number_format($receivedQuantity, 3, '.', ''),
            strtolower(trim($unitCode)),
            $unitCost !== null ? number_format($unitCost, 3, '.', '') : '',
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $lines
     * @param  array<int, Ingredient>  $ingredients
     */
    private function replacePurchaseOrderLines(PurchaseOrder $order, array $lines, array $ingredients): void
    {
        PurchaseOrderLine::query()->where('purchase_order_id', (int) $order->purchase_order_id)->delete();

        foreach ($lines as $index => $line) {
            /** @var Ingredient $ingredient */
            $ingredient = $ingredients[(int) $line['ingredient_id']];
            $unitCode = $this->resolveIngredientUnitCode(
                $ingredient,
                $line['unit_code'] ?? null,
                'lines.'.$index.'.unit_code'
            );

            PurchaseOrderLine::query()->create([
                'purchase_order_id' => (int) $order->purchase_order_id,
                'ingredient_id' => (int) $ingredient->ingredient_id,
                'ordered_quantity' => number_format((float) $line['ordered_quantity'], 3, '.', ''),
                'received_quantity' => '0.000',
                'unit_code' => $unitCode,
                'unit_cost' => array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null
                    ? number_format((float) $line['unit_cost'], 3, '.', '')
                    : null,
                'notes' => $this->normalizeNullableString($line['notes'] ?? null),
                'sort_order' => (int) ($line['sort_order'] ?? (($index + 1) * 10)),
            ]);
        }
    }

    private function normalizeWritableStatus(string $value, bool $allowCancelled): PurchaseOrderStatus
    {
        $status = PurchaseOrderStatus::from($value);

        if (! $allowCancelled && $status === PurchaseOrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'purchase_order_status' => 'Purchase orders cannot be created directly as Cancelled.',
            ]);
        }

        if (in_array($status, [PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received], true)) {
            throw ValidationException::withMessages([
                'purchase_order_status' => 'Received statuses are managed by the receiving lifecycle.',
            ]);
        }

        return $status;
    }

    private function generatePurchaseOrderCode(): string
    {
        return 'PO-'.Carbon::now('UTC')->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function generateReceiptCode(): string
    {
        return 'GRN-'.Carbon::now('UTC')->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function resolveIngredientUnitCode(Ingredient $ingredient, mixed $requestedUnitCode, string $field): string
    {
        return $this->resolveExpectedUnitCode(
            trim((string) $ingredient->unit_code),
            $requestedUnitCode,
            $field,
            'Ingredient unit code must be set before purchase order lines can be created.'
        );
    }

    private function resolvePurchaseOrderLineUnitCode(PurchaseOrderLine $orderLine, mixed $requestedUnitCode, string $field): string
    {
        return $this->resolveExpectedUnitCode(
            trim((string) $orderLine->unit_code),
            $requestedUnitCode,
            $field,
            'Purchase order line unit code must be set before receiving stock.'
        );
    }

    private function resolveExpectedUnitCode(
        string $expectedUnitCode,
        mixed $requestedUnitCode,
        string $field,
        string $missingUnitMessage
    ): string {
        if ($expectedUnitCode === '') {
            throw ValidationException::withMessages([
                $field => $missingUnitMessage,
            ]);
        }

        $normalizedRequestedUnitCode = trim((string) ($requestedUnitCode ?? ''));
        if ($normalizedRequestedUnitCode !== '' && ! $this->unitCodesMatch($expectedUnitCode, $normalizedRequestedUnitCode)) {
            throw ValidationException::withMessages([
                $field => sprintf('Unit code must match ingredient unit [%s].', $expectedUnitCode),
            ]);
        }

        return $expectedUnitCode;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function unitCodesMatch(string $expectedUnitCode, string $actualUnitCode): bool
    {
        return strtolower(trim($expectedUnitCode)) === strtolower(trim($actualUnitCode));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveSupplierSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        return match ($sortBy) {
            'code' => ['code', $direction],
            'supplier_id' => ['supplier_id', $direction],
            'updated_at' => ['updated_at', $direction],
            default => ['name', $direction],
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolvePurchaseOrderSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'ordered_at' => ['purchase_orders.ordered_at', $direction],
            'expected_at' => ['purchase_orders.expected_at', $direction],
            'purchase_order_id' => ['purchase_orders.purchase_order_id', $direction],
            'purchase_order_status' => ['purchase_orders.purchase_order_status', $direction],
            'supplier_id' => ['purchase_orders.supplier_id', $direction],
            'branch_id' => ['purchase_orders.branch_id', $direction],
            default => ['purchase_orders.created_at', $direction],
        };
    }
}
