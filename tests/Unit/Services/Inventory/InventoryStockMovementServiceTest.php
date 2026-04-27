<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inventory;

use App\Modules\InventoryProcurement\Application\UseCases\Inventory\InventoryStockMovementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class InventoryStockMovementServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    private function makeService(): InventoryStockMovementService
    {
        return app(InventoryStockMovementService::class);
    }

    public function test_inventory_adjustment_decrease_rejects_when_insufficient_stock(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'INV-SVC-NEG',
            'branch_name' => 'Inventory Negative Guard Branch',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-SVC-NEG',
            'name' => 'Negative Guard Rice',
            'unit_code' => 'kg',
        ]);

        try {
            $this->makeService()->recordMovement($ingredientId, [
                'branch_id' => $branchId,
                'movement_type' => 'AdjustmentDecrease',
                'quantity' => '1.000',
                'unit_code' => 'kg',
                'notes' => 'Manual shrinkage',
            ], 99);

            self::fail('Expected insufficient stock adjustment decrease to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('quantity', $exception->errors());
            self::assertStringContainsString('below zero', $exception->errors()['quantity'][0] ?? '');
        }

        self::assertSame(
            0,
            (int) DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('branch_id', $branchId)
                ->count()
        );
        self::assertSame('0.000', $this->makeService()->currentStockOnHand($ingredientId, $branchId));
    }

    public function test_purchase_receipt_increases_stock_then_stockout_succeeds(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'INV-SVC-STOCKOUT',
            'branch_name' => 'Inventory Stockout Branch',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-SVC-STOCKOUT',
            'name' => 'Stockout Rice',
            'unit_code' => 'kg',
        ]);

        $stockIn = $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockIn',
            'quantity' => '5.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'GRN-SVC-STOCKOUT-0001:10',
        ], 101);

        $stockOut = $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockOut',
            'quantity' => '3.000',
            'unit_code' => 'kg',
            'notes' => 'Manual stock out after receipt',
        ], 102);

        self::assertSame('StockIn', (string) $stockIn->movement_type);
        self::assertSame('StockOut', (string) $stockOut->movement_type);
        self::assertSame('-3.000', number_format((float) $stockOut->quantity_delta, 3, '.', ''));
        self::assertSame('2.000', $this->makeService()->currentStockOnHand($ingredientId, $branchId));
    }

    public function test_purchase_receipt_reference_replay_returns_existing_stock_movement(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'INV-SVC',
            'branch_name' => 'Inventory Service Branch',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-SVC-REPLAY',
            'name' => 'Replay Safe Flour',
            'unit_code' => 'kg',
        ]);

        $firstMovement = $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockIn',
            'quantity' => '5.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'GRN-SVC-0001:10',
            'notes' => 'Initial goods receipt',
            'created_at' => $this->nowUtc()->copy()->subMinute(),
        ], 101);

        $replayedMovement = $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockIn',
            'quantity' => '5.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'GRN-SVC-0001:10',
            'notes' => 'Retried goods receipt request',
            'created_at' => $this->nowUtc(),
        ], 202);

        self::assertSame((int) $firstMovement->movement_id, (int) $replayedMovement->movement_id);
        self::assertSame(
            1,
            (int) DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->where('reference_id', 'GRN-SVC-0001:10')
                ->count()
        );
        self::assertSame(
            $branchId,
            (int) DB::table('ingredient_stock_movements')
                ->where('movement_id', $firstMovement->movement_id)
                ->value('branch_id')
        );
    }

    public function test_system_generated_reference_rejects_payload_drift_on_replay(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'INV-SVC-DRIFT',
            'branch_name' => 'Inventory Drift Branch',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-SVC-DRIFT',
            'name' => 'Replay Drift Chili',
            'unit_code' => 'kg',
        ]);

        $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockIn',
            'quantity' => '5.000',
            'unit_code' => 'kg',
            'notes' => 'Seed stock for replay drift guard',
        ], 302);

        $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => 'StockOut',
            'quantity' => '2.000',
            'unit_code' => 'kg',
            'reference_type' => 'ReservationOrderItemConsumption',
            'reference_id' => '9001:77:15',
            'notes' => 'Consumed once',
        ], 303);

        try {
            $this->makeService()->recordMovement($ingredientId, [
                'branch_id' => $branchId,
                'movement_type' => 'StockOut',
                'quantity' => '3.000',
                'unit_code' => 'kg',
                'reference_type' => 'ReservationOrderItemConsumption',
                'reference_id' => '9001:77:15',
                'notes' => 'Conflicting retry',
            ], 404);

            self::fail('Expected replay drift to be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'System stock movement reference [ReservationOrderItemConsumption:9001:77:15] is already recorded with different movement details.',
                $exception->errors()['reference_id'][0] ?? null
            );
        }

        self::assertSame(
            1,
            (int) DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'ReservationOrderItemConsumption')
                ->where('reference_id', '9001:77:15')
                ->count()
        );
    }

    public function test_replay_safe_reference_cannot_cross_branch_scope(): void
    {
        $firstBranchId = $this->createBranch([
            'branch_code' => 'INV-SVC-BR-A',
            'branch_name' => 'Inventory Replay Branch A',
        ]);
        $secondBranchId = $this->createBranch([
            'branch_code' => 'INV-SVC-BR-B',
            'branch_name' => 'Inventory Replay Branch B',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-SVC-BRANCH-REPLAY',
            'name' => 'Replay Branch Rice',
            'unit_code' => 'kg',
        ]);

        $this->makeService()->recordMovement($ingredientId, [
            'branch_id' => $firstBranchId,
            'movement_type' => 'StockIn',
            'quantity' => '4.000',
            'unit_code' => 'kg',
            'reference_type' => 'PurchaseReceipt',
            'reference_id' => 'GRN-SVC-BRANCH-0001:10',
        ], 505);

        try {
            $this->makeService()->recordMovement($ingredientId, [
                'branch_id' => $secondBranchId,
                'movement_type' => 'StockIn',
                'quantity' => '4.000',
                'unit_code' => 'kg',
                'reference_type' => 'PurchaseReceipt',
                'reference_id' => 'GRN-SVC-BRANCH-0001:10',
            ], 606);

            self::fail('Expected replay across branches to be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'System stock movement reference [PurchaseReceipt:GRN-SVC-BRANCH-0001:10] is already recorded with different movement details.',
                $exception->errors()['reference_id'][0] ?? null
            );
        }

        self::assertSame(
            1,
            (int) DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->where('reference_type', 'PurchaseReceipt')
                ->where('reference_id', 'GRN-SVC-BRANCH-0001:10')
                ->count()
        );
        self::assertSame(
            '4.000',
            number_format((float) $this->makeService()->currentStockOnHand($ingredientId, $firstBranchId), 3, '.', '')
        );
        self::assertSame(
            '0.000',
            number_format((float) $this->makeService()->currentStockOnHand($ingredientId, $secondBranchId), 3, '.', '')
        );
    }
}
