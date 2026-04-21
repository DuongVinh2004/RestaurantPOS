<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\InventoryProcurement\Http\Requests\Admin\CreatePurchaseOrderReceiptRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\CreatePurchaseOrderRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\CreateSupplierRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\ListPurchaseOrdersRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\ListSuppliersRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\UpdatePurchaseOrderRequest;
use App\Modules\InventoryProcurement\Http\Requests\Admin\UpdateSupplierRequest;
use App\Modules\InventoryProcurement\Http\Resources\Admin\PurchaseOrderResource;
use App\Modules\InventoryProcurement\Http\Resources\Admin\PurchaseReceiptResource;
use App\Modules\InventoryProcurement\Http\Resources\Admin\SupplierResource;
use App\Modules\InventoryProcurement\Application\UseCases\Procurement\ProcurementManagementService;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    public function __construct(
        private readonly ProcurementManagementService $purchasingService,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    public function listSuppliers(ListSuppliersRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        $validated = $request->validated();
        $paginator = $this->purchasingService->paginateSuppliers($validated);

        return response()->json([
            'data' => SupplierResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'is_active' => array_key_exists('is_active', $validated) && $validated['is_active'] !== null ? (bool) $validated['is_active'] : null,
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? 'name'),
                    'by' => (string) ($validated['sort_by'] ?? 'name'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                ],
                ListingMetaFactory::contract(
                    ['is_active', 'q'],
                    ['name', 'code', 'supplier_id', 'updated_at'],
                    'name',
                    true,
                    (int) config('booking.admin_inventory_page_max', 100),
                    [
                        'is_active' => 'filter[is_active]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    public function showSupplier(int $id, ListSuppliersRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $supplier = $this->purchasingService->findSupplier($id);
        } catch (ModelNotFoundException) {
            return $this->supplierNotFoundResponse($request);
        }

        return response()->json([
            'data' => (new SupplierResource($supplier))->toArray($request),
        ]);
    }

    public function createSupplier(CreateSupplierRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        $supplier = $this->purchasingService->createSupplier($request->validated());

        return response()->json([
            'data' => (new SupplierResource($supplier))->toArray($request),
        ], 201);
    }

    public function updateSupplier(int $id, UpdateSupplierRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $supplier = $this->purchasingService->updateSupplier($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->supplierNotFoundResponse($request);
        }

        return response()->json([
            'data' => (new SupplierResource($supplier))->toArray($request),
        ]);
    }

    public function listPurchaseOrders(ListPurchaseOrdersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertInventoryUpliftEnabled(
            $request,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );
        $paginator = $this->purchasingService->paginatePurchaseOrders($validated);

        return response()->json([
            'data' => PurchaseOrderResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'supplier_id' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
                    'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                    'purchase_order_status' => $validated['purchase_order_status'] ?? null,
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-created_at'),
                    'by' => (string) ($validated['sort_by'] ?? 'created_at'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    ['supplier_id', 'branch_id', 'purchase_order_status', 'q'],
                    ['created_at', 'ordered_at', 'expected_at', 'purchase_order_id', 'purchase_order_status', 'supplier_id', 'branch_id'],
                    '-created_at',
                    true,
                    (int) config('booking.admin_inventory_page_max', 100),
                    [
                        'supplier_id' => 'filter[supplier_id]',
                        'branch_id' => 'filter[branch_id]',
                        'purchase_order_status' => 'filter[purchase_order_status]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    public function showPurchaseOrder(int $id, ListPurchaseOrdersRequest $request): JsonResponse
    {
        $branchId = $this->resolvePurchaseOrderBranchId($id);
        if ($branchId !== null) {
            $this->assertInventoryUpliftEnabled($request, $branchId);
        }

        try {
            $order = $this->purchasingService->findPurchaseOrder($id);
        } catch (ModelNotFoundException) {
            return $this->purchaseOrderNotFoundResponse($request);
        }

        return response()->json([
            'data' => (new PurchaseOrderResource($order))->toArray($request),
        ]);
    }

    public function createPurchaseOrder(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertInventoryUpliftEnabled(
            $request,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );

        $order = $this->purchasingService->createPurchaseOrder(
            $validated,
            $this->resolveStaffActorUserId($request)
        );

        return response()->json([
            'data' => (new PurchaseOrderResource($order))->toArray($request),
        ], 201);
    }

    public function updatePurchaseOrder(int $id, UpdatePurchaseOrderRequest $request): JsonResponse
    {
        $branchId = $this->resolvePurchaseOrderBranchId($id);
        if ($branchId !== null) {
            $this->assertInventoryUpliftEnabled($request, $branchId);
        }

        try {
            $order = $this->purchasingService->updatePurchaseOrder(
                $id,
                $request->validated(),
                $this->resolveStaffActorUserId($request)
            );
        } catch (ModelNotFoundException) {
            return $this->purchaseOrderNotFoundResponse($request);
        }

        return response()->json([
            'data' => (new PurchaseOrderResource($order))->toArray($request),
        ]);
    }

    public function listPurchaseOrderReceipts(int $id, ListPurchaseOrdersRequest $request): JsonResponse
    {
        $branchId = $this->resolvePurchaseOrderBranchId($id);
        if ($branchId !== null) {
            $this->assertInventoryUpliftEnabled($request, $branchId);
        }

        try {
            $result = $this->purchasingService->listPurchaseOrderReceipts($id);
        } catch (ModelNotFoundException) {
            return $this->purchaseOrderNotFoundResponse($request);
        }

        return response()->json([
            'data' => PurchaseReceiptResource::collection($result['receipts'])->toArray($request),
            'meta' => [
                'purchase_order' => (new PurchaseOrderResource($result['order']))->toArray($request),
                'count' => $result['receipts']->count(),
            ],
        ]);
    }

    public function createPurchaseOrderReceipt(int $id, CreatePurchaseOrderReceiptRequest $request): JsonResponse
    {
        $branchId = $this->resolvePurchaseOrderBranchId($id);
        if ($branchId !== null) {
            $this->assertInventoryUpliftEnabled($request, $branchId);
        }

        try {
            $result = $this->purchasingService->createReceipt(
                $id,
                $request->validated(),
                $this->resolveStaffActorUserId($request)
            );
        } catch (ModelNotFoundException) {
            return $this->purchaseOrderNotFoundResponse($request);
        }

        return response()->json([
            'data' => (new PurchaseReceiptResource($result['receipt']))->toArray($request),
            'meta' => [
                'purchase_order' => (new PurchaseOrderResource($result['order']))->toArray($request),
            ],
        ], 201);
    }

    private function supplierNotFoundResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            'Supplier not found.',
        );
    }

    private function purchaseOrderNotFoundResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            'Purchase order not found.',
        );
    }

    private function resolveStaffActorUserId(mixed $request): ?int
    {
        $actor = $request->attributes->get('staff_actor_user_id');

        return is_numeric($actor) ? (int) $actor : null;
    }

    private function resolvePurchaseOrderBranchId(int $purchaseOrderId): ?int
    {
        $branchId = DB::table('purchase_orders')
            ->where('purchase_order_id', $purchaseOrderId)
            ->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }

    private function assertInventoryUpliftEnabled(Request $request, ?int $branchId = null): void
    {
        $feature = $this->featureFlags->resolve('inventory.uplift', $branchId);
        if ($feature['enabled'] ?? false) {
            return;
        }

        throw new HttpResponseException(ApiErrorResponse::json(
            $request,
            403,
            'feature_disabled',
            (string) ($feature['message'] ?? 'Inventory uplift features are disabled for this rollout.'),
            extra: [
                'feature_key' => (string) ($feature['feature_key'] ?? 'inventory.uplift'),
                'branch_id' => $feature['branch_id'] ?? $branchId,
            ],
        ));
    }
}
