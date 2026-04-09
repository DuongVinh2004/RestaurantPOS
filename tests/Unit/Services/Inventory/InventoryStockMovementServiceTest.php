<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Inventory;

use App\Services\Inventory\InventoryStockMovementService;
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
}
