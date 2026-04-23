<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\InventoryProcurement\Application\Workflows\PurchaseOrderReconciliationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminPurchasingFoundationHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_admin_can_manage_supplier_purchase_order_and_receiving_foundation(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-purchasing-manage-key');
        $riceIngredientId = $this->createIngredient([
            'code' => 'ING-RCV-RICE',
            'name' => 'Receiving Rice',
            'unit_code' => 'kg',
        ]);
        $oilIngredientId = $this->createIngredient([
            'code' => 'ING-RCV-OIL',
            'name' => 'Receiving Oil',
            'unit_code' => 'l',
        ]);

        $createSupplier = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-supplier-create'))
            ->postJson('/api/v1/admin/inventory/suppliers', [
                'code' => 'SUP-NORTH-01',
                'name' => 'North Farm Supply',
                'contact_name' => 'Ms Lan',
                'phone' => '0900000001',
                'email' => 'north-supply@example.test',
                'notes' => 'Preferred dry goods vendor',
            ]);

        $createSupplier->assertCreated()
            ->assertJsonPath('data.code', 'SUP-NORTH-01')
            ->assertJsonPath('data.name', 'North Farm Supply')
            ->assertJsonPath('data.is_active', true);

        $supplierId = (int) $createSupplier->json('data.supplier_id');

        $updateSupplier = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-supplier-update'))
            ->patchJson('/api/v1/admin/inventory/suppliers/'.$supplierId, [
                'phone' => '0900000009',
                'notes' => 'Primary purchasing contact for dry goods',
            ]);

        $updateSupplier->assertOk()
            ->assertJsonPath('data.phone', '0900000009')
            ->assertJsonPath('data.notes', 'Primary purchasing contact for dry goods');

        $createPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-FOUND-0001',
                'expected_at' => $this->nowUtc()->copy()->addDays(1)->toIso8601String(),
                'supplier_reference' => 'SUP-QUOTE-77',
                'notes' => 'Patch 17 foundation order',
                'lines' => [
                    [
                        'ingredient_id' => $riceIngredientId,
                        'ordered_quantity' => '20.000',
                        'unit_code' => 'kg',
                        'unit_cost' => '12.500',
                        'sort_order' => 10,
                    ],
                    [
                        'ingredient_id' => $oilIngredientId,
                        'ordered_quantity' => '10.000',
                        'unit_code' => 'l',
                        'unit_cost' => '40.000',
                        'sort_order' => 20,
                    ],
                ],
            ]);

        $createPurchaseOrder->assertCreated()
            ->assertJsonPath('data.order_code', 'PO-FOUND-0001')
            ->assertJsonPath('data.purchase_order_status', 'Ordered')
            ->assertJsonPath('data.summary.line_count', 2)
            ->assertJsonPath('data.summary.receipt_count', 0)
            ->assertJsonPath('data.lines.0.ingredient.ingredient_id', $riceIngredientId)
            ->assertJsonPath('data.lines.1.ingredient.ingredient_id', $oilIngredientId);

        $purchaseOrderId = (int) $createPurchaseOrder->json('data.purchase_order_id');
        $purchaseOrderBranchId = (int) DB::table('purchase_orders')->where('purchase_order_id', $purchaseOrderId)->value('branch_id');
        $ricePoLineId = (int) $createPurchaseOrder->json('data.lines.0.po_line_id');
        $oilPoLineId = (int) $createPurchaseOrder->json('data.lines.1.po_line_id');

        $partialReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-receipt-1'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-FOUND-0001',
                'supplier_document_no' => 'DEL-1001',
                'notes' => 'First delivery',
                'lines' => [
                    [
                        'purchase_order_line_id' => $ricePoLineId,
                        'received_quantity' => '12.000',
                        'unit_cost' => '12.500',
                    ],
                ],
            ]);

        $partialReceipt->assertCreated()
            ->assertJsonPath('data.receipt_code', 'GRN-FOUND-0001')
            ->assertJsonPath('data.summary.line_count', 1)
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'PartiallyReceived')
            ->assertJsonPath('meta.purchase_order.summary.receipt_count', 1)
            ->assertJsonPath('meta.purchase_order.summary.received_total_quantity', '12.000');

        $finalReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-receipt-2'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-FOUND-0002',
                'supplier_document_no' => 'DEL-1002',
                'notes' => 'Final delivery',
                'lines' => [
                    [
                        'purchase_order_line_id' => $ricePoLineId,
                        'received_quantity' => '8.000',
                        'unit_cost' => '12.500',
                    ],
                    [
                        'purchase_order_line_id' => $oilPoLineId,
                        'received_quantity' => '10.000',
                        'unit_cost' => '40.000',
                    ],
                ],
            ]);

        $finalReceipt->assertCreated()
            ->assertJsonPath('data.summary.line_count', 2)
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received')
            ->assertJsonPath('meta.purchase_order.summary.receipt_count', 2)
            ->assertJsonPath('meta.purchase_order.summary.ordered_total_quantity', '30.000')
            ->assertJsonPath('meta.purchase_order.summary.received_total_quantity', '30.000');

        $showPurchaseOrder = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId);

        $showPurchaseOrder->assertOk()
            ->assertJsonPath('data.purchase_order_status', 'Received')
            ->assertJsonPath('data.summary.receipt_count', 2)
            ->assertJsonPath('data.lines.0.received_quantity', '20.000')
            ->assertJsonPath('data.lines.1.received_quantity', '10.000');

        $receipts = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts');

        $receipts->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.receipt_code', 'GRN-FOUND-0002')
            ->assertJsonPath('data.1.receipt_code', 'GRN-FOUND-0001');

        $riceMovements = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$riceIngredientId.'/movements');

        $riceMovements->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.ingredient.stock_on_hand', '20.000')
            ->assertJsonPath('data.0.reference.type', 'PurchaseReceipt');

        $this->assertSame($purchaseOrderBranchId, (int) $riceMovements->json('data.0.branch_id'));
        $this->assertSame($purchaseOrderBranchId, (int) $riceMovements->json('data.1.branch_id'));

        $oilIngredient = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$oilIngredientId);

        $oilIngredient->assertOk()
            ->assertJsonPath('data.stock.on_hand', '10.000');

        self::assertSame($adminId, $adminId);
    }

    public function test_receiving_cannot_exceed_remaining_purchase_order_quantity(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-purchasing-overreceive-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-OVR-01',
            'name' => 'Over Receive Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-OVR-01',
            'name' => 'Over Receive Rice',
            'unit_code' => 'kg',
        ]);

        $createPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-over-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-OVR-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $createPurchaseOrder->assertCreated();

        $purchaseOrderId = (int) $createPurchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $createPurchaseOrder->json('data.lines.0.po_line_id');

        $overReceive = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-over-receive'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '6.000',
                    ],
                ],
            ]);

        $overReceive->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.received_quantity']);

        self::assertSame($adminId, $adminId);
    }

    public function test_non_admin_staff_cannot_access_admin_purchasing_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);

        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['reservation.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeadersForTest($staffId, 'plain-staff-purchasing-key'))
            ->getJson('/api/v1/admin/inventory/purchase-orders');

        $response->assertForbidden()
            ->assertJsonPath('required_capability', 'inventory.manage');
    }

    public function test_branch_flag_can_disable_purchase_order_rollout_for_a_single_branch(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-flag-off-key');
        $branchId = $this->createBranch([
            'branch_code' => 'INV-OFF',
            'branch_name' => 'Inventory Off',
        ]);
        $supplierId = $this->createSupplier([
            'code' => 'SUP-FLAG-01',
            'name' => 'Flagged Supplier',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-FLAG-01',
            'name' => 'Flagged Ingredient',
            'unit_code' => 'kg',
        ]);

        $this->upsertFeatureFlagOverride(
            'inventory.uplift',
            false,
            'testing',
            $branchId,
            ['reason' => 'branch still using manual receiving'],
        );

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-flag-off'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'order_code' => 'PO-FLAG-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature_key', 'inventory.uplift')
            ->assertJsonPath('branch_id', $branchId);
    }

    public function test_purchase_order_lines_and_receipts_require_matching_ingredient_units(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-unit-guard-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-UNIT-01',
            'name' => 'Unit Guard Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-UNIT-PO',
            'name' => 'Guarded Flour',
            'unit_code' => 'kg',
        ]);

        $createInvalidPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-unit-invalid'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-UNIT-INVALID',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'g',
                    ],
                ],
            ]);

        $createInvalidPurchaseOrder->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.unit_code']);

        $createPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-unit-valid'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-UNIT-VALID',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $createPurchaseOrder->assertCreated()
            ->assertJsonPath('data.lines.0.unit_code', 'kg');

        $purchaseOrderId = (int) $createPurchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $createPurchaseOrder->json('data.lines.0.po_line_id');

        $createInvalidReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-unit-receipt-invalid'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-UNIT-INVALID',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '2.000',
                        'unit_code' => 'g',
                    ],
                ],
            ]);

        $createInvalidReceipt->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.unit_code']);

        $this->assertSame(
            0,
            DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count()
        );
        $this->assertSame(
            '0.000',
            number_format((float) DB::table('purchase_order_lines')->where('po_line_id', $poLineId)->value('received_quantity'), 3, '.', '')
        );
        $this->assertSame(
            0,
            DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->count()
        );
    }

    public function test_purchase_orders_cannot_receive_in_draft_or_override_status_after_receiving_starts(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-status-guard-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-STATUS-01',
            'name' => 'Status Guard Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-STATUS-01',
            'name' => 'Status Guard Rice',
            'unit_code' => 'kg',
        ]);

        $draftPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-draft-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-DRAFT-0001',
                'purchase_order_status' => 'Draft',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                    ],
                ],
            ]);

        $draftPurchaseOrder->assertCreated()
            ->assertJsonPath('data.purchase_order_status', 'Draft');

        $draftPurchaseOrderId = (int) $draftPurchaseOrder->json('data.purchase_order_id');
        $draftPoLineId = (int) $draftPurchaseOrder->json('data.lines.0.po_line_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-draft-receipt'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$draftPurchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-DRAFT-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $draftPoLineId,
                        'received_quantity' => '1.000',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_status']);

        $orderedPurchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-ordered-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-ORDERED-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                    ],
                ],
            ]);

        $orderedPurchaseOrder->assertCreated()
            ->assertJsonPath('data.purchase_order_status', 'Ordered');

        $orderedPurchaseOrderId = (int) $orderedPurchaseOrder->json('data.purchase_order_id');
        $orderedPoLineId = (int) $orderedPurchaseOrder->json('data.lines.0.po_line_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-ordered-receipt'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$orderedPurchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-ORDERED-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $orderedPoLineId,
                        'received_quantity' => '2.000',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'PartiallyReceived');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-status-update-after-receipt'))
            ->patchJson('/api/v1/admin/inventory/purchase-orders/'.$orderedPurchaseOrderId, [
                'purchase_order_status' => 'Draft',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_status']);

        $this->assertSame(
            'PartiallyReceived',
            (string) DB::table('purchase_orders')->where('purchase_order_id', $orderedPurchaseOrderId)->value('purchase_order_status')
        );
        $this->assertSame(
            1,
            DB::table('purchase_receipts')->where('purchase_order_id', $orderedPurchaseOrderId)->count()
        );
    }

    public function test_purchase_order_receipt_same_idempotency_key_replays_without_duplicate_stock(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-idem-replay-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-IDEM-01',
            'name' => 'Replay Safe Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-IDEM-01',
            'name' => 'Replay Safe Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-replay-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-IDEM-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');
        $receiptHeaders = $this->withIdempotencyKey($headers, 'idem-admin-po-replay-receipt');
        $payload = [
            'receipt_code' => 'GRN-IDEM-0001',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLineId,
                    'received_quantity' => '5.000',
                ],
            ],
        ];

        $firstReceipt = $this->withHeaders($receiptHeaders)
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', $payload);
        $secondReceipt = $this->withHeaders($receiptHeaders)
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', $payload);

        $firstReceipt->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');
        $secondReceipt->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');

        $this->assertSame($firstReceipt->json('data.receipt_id'), $secondReceipt->json('data.receipt_id'));
        $this->assertSame($firstReceipt->json('data.lines.0.stock_movement_id'), $secondReceipt->json('data.lines.0.stock_movement_id'));
        $this->assertSame(
            1,
            DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count()
        );
        $this->assertSame(
            1,
            DB::table('purchase_receipt_lines')->where('purchase_order_line_id', $poLineId)->count()
        );
        $this->assertSame(
            1,
            DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->count()
        );
    }

    public function test_receipt_code_duplicate_replays_without_duplicate_receipt_or_stock_movement(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-receipt-code-replay-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-RCODE-01',
            'name' => 'Receipt Code Replay Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RCODE-01',
            'name' => 'Receipt Code Replay Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-RCODE-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');
        $payload = [
            'receipt_code' => 'GRN-RCODE-0001',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLineId,
                    'received_quantity' => '5.000',
                ],
            ],
        ];

        $firstReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-receipt-a'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', $payload);

        $replayedReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-receipt-b'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', $payload);

        $firstReceipt->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');
        $replayedReceipt->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');

        $this->assertSame($firstReceipt->json('data.receipt_id'), $replayedReceipt->json('data.receipt_id'));
        $this->assertSame($firstReceipt->json('data.lines.0.stock_movement_id'), $replayedReceipt->json('data.lines.0.stock_movement_id'));
        $this->assertSame(1, DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count());
        $this->assertSame(1, DB::table('purchase_receipt_lines')->where('purchase_order_line_id', $poLineId)->count());
        $this->assertSame(
            1,
            DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->count()
        );
    }

    public function test_receipt_code_duplicate_with_drifted_lines_is_rejected(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-receipt-code-drift-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-RCODE-DRIFT-01',
            'name' => 'Receipt Code Drift Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RCODE-DRIFT-01',
            'name' => 'Receipt Code Drift Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-drift-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-RCODE-DRIFT-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-drift-receipt-a'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-RCODE-DRIFT-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '2.000',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'PartiallyReceived');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-rcode-drift-receipt-b'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-RCODE-DRIFT-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '3.000',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_code']);

        $this->assertSame(1, DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count());
        $this->assertSame(
            '2.000',
            number_format((float) DB::table('purchase_order_lines')->where('po_line_id', $poLineId)->value('received_quantity'), 3, '.', '')
        );
    }

    public function test_supplier_document_duplicate_replays_without_duplicate_receipt_or_stock_movement(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-supplier-doc-replay-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-DOC-01',
            'name' => 'Document Replay Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-DOC-01',
            'name' => 'Document Replay Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-DOC-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');
        $payload = [
            'receipt_code' => 'GRN-DOC-0001',
            'supplier_document_no' => 'DEL-DOC-0001',
            'lines' => [
                [
                    'purchase_order_line_id' => $poLineId,
                    'received_quantity' => '5.000',
                ],
            ],
        ];

        $firstReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-receipt-a'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', $payload);

        $replayedReceipt = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-receipt-b'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-DOC-0002',
                'supplier_document_no' => 'DEL-DOC-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '5.000',
                    ],
                ],
            ]);

        $firstReceipt->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');
        $replayedReceipt->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'Received');

        $this->assertSame($firstReceipt->json('data.receipt_id'), $replayedReceipt->json('data.receipt_id'));
        $this->assertSame($firstReceipt->json('data.lines.0.stock_movement_id'), $replayedReceipt->json('data.lines.0.stock_movement_id'));
        $this->assertSame(1, DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count());
        $this->assertSame(1, DB::table('purchase_receipt_lines')->where('purchase_order_line_id', $poLineId)->count());
        $this->assertSame(
            1,
            DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->count()
        );
    }

    public function test_supplier_document_duplicate_with_drifted_lines_is_rejected(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-supplier-doc-drift-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-DOC-DRIFT-01',
            'name' => 'Document Drift Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-DOC-DRIFT-01',
            'name' => 'Document Drift Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-drift-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-DOC-DRIFT-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-drift-receipt-a'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-DOC-DRIFT-0001',
                'supplier_document_no' => 'DEL-DOC-DRIFT-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '2.000',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.purchase_order.purchase_order_status', 'PartiallyReceived');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-doc-drift-receipt-b'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-DOC-DRIFT-0002',
                'supplier_document_no' => 'DEL-DOC-DRIFT-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '3.000',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_document_no']);

        $this->assertSame(1, DB::table('purchase_receipts')->where('purchase_order_id', $purchaseOrderId)->count());
        $this->assertSame(
            '2.000',
            number_format((float) DB::table('purchase_order_lines')->where('po_line_id', $poLineId)->value('received_quantity'), 3, '.', '')
        );
    }

    public function test_purchase_receipt_duplicate_stock_movement_lineage_is_blocked_by_database_guard(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-reconcile-lineage-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-RECON-01',
            'name' => 'Reconcile Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RECON-01',
            'name' => 'Reconcile Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-recon-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-RECON-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');
        $purchaseOrderBranchId = (int) DB::table('purchase_orders')->where('purchase_order_id', $purchaseOrderId)->value('branch_id');
        $poLineId = (int) $purchaseOrder->json('data.lines.0.po_line_id');

        $receiptResponse = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-recon-receipt'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-RECON-0001',
                'supplier_document_no' => 'DEL-RECON-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => $poLineId,
                        'received_quantity' => '5.000',
                    ],
                ],
            ]);

        $receiptResponse->assertCreated();

        $stockMovementId = (int) $receiptResponse->json('data.lines.0.stock_movement_id');
        $receiptLineId = (int) $receiptResponse->json('data.lines.0.receipt_line_id');

        try {
            DB::table('ingredient_stock_movements')->insert([
                'branch_id' => $purchaseOrderBranchId,
                'ingredient_id' => $ingredientId,
                'movement_type' => 'StockIn',
                'quantity_delta' => '5.000',
                'unit_code' => 'kg',
                'reference_type' => 'PurchaseReceipt',
                'reference_id' => 'GRN-RECON-0001:'.$poLineId,
                'notes' => 'Duplicate movement for reconciliation test',
                'created_by' => null,
                'created_at' => $this->nowUtc(),
            ]);

            $this->fail('Expected duplicate ingredient stock movement lineage insert to be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('ingredient_stock_movements', strtolower($exception->getMessage()));
        }

        $report = app(PurchaseOrderReconciliationService::class)->report($purchaseOrderId);

        $this->assertSame(0, (int) $report['issue_count']);
        $this->assertSame(0, (int) $report['movement_issue_count']);
        $this->assertSame($stockMovementId, (int) DB::table('purchase_receipt_lines')->where('receipt_line_id', $receiptLineId)->value('stock_movement_id'));
    }

    public function test_purchase_order_reconciliation_reports_missing_stock_movement_lineage(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-reconcile-missing-lineage-key');
        $supplierId = $this->createSupplier([
            'code' => 'SUP-RECON-MISS-01',
            'name' => 'Missing Lineage Supply',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RECON-MISS-01',
            'name' => 'Missing Lineage Rice',
            'unit_code' => 'kg',
        ]);

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-recon-miss-create'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'supplier_id' => $supplierId,
                'order_code' => 'PO-RECON-MISS-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '5.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated();

        $purchaseOrderId = (int) $purchaseOrder->json('data.purchase_order_id');

        $receiptResponse = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-po-recon-miss-receipt'))
            ->postJson('/api/v1/admin/inventory/purchase-orders/'.$purchaseOrderId.'/receipts', [
                'receipt_code' => 'GRN-RECON-MISS-0001',
                'supplier_document_no' => 'DEL-RECON-MISS-0001',
                'lines' => [
                    [
                        'purchase_order_line_id' => (int) $purchaseOrder->json('data.lines.0.po_line_id'),
                        'received_quantity' => '5.000',
                    ],
                ],
            ]);

        $receiptResponse->assertCreated();

        $receiptLineId = (int) $receiptResponse->json('data.lines.0.receipt_line_id');
        $stockMovementId = (int) $receiptResponse->json('data.lines.0.stock_movement_id');

        DB::table('ingredient_stock_movements')
            ->where('movement_id', $stockMovementId)
            ->delete();

        $report = app(PurchaseOrderReconciliationService::class)->report($purchaseOrderId);

        $this->assertSame(1, (int) $report['issue_count']);
        $this->assertSame(1, (int) $report['movement_issue_count']);
        $this->assertSame('receipt_line_stock_movement_missing', data_get($report, 'issues.0.type'));
        $this->assertSame($stockMovementId, (int) DB::table('purchase_receipt_lines')->where('receipt_line_id', $receiptLineId)->value('stock_movement_id'));
    }

    public function test_missing_supplier_and_purchase_order_return_standardized_not_found_envelope(): void
    {
        [, $headers] = $this->adminHeaders('admin-purchasing-missing-resource-key');

        $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-admin-supplier-404',
        ]))
            ->getJson('/api/v1/admin/inventory/suppliers/999999')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-supplier-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-supplier-404');

        $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-admin-purchase-order-404',
        ]))
            ->getJson('/api/v1/admin/inventory/purchase-orders/999999')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-purchase-order-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-purchase-order-404');
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffHeadersForTest($adminId, $apiKey)];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeadersForTest(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
