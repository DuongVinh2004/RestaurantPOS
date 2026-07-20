<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

#[Group('mysql-runtime')]
class InventoryStockEquationMysqlConcurrencyTest extends TestCase
{
    use BuildsBookingScenario;

    /** @var array<string,int> */
    private array $fixtureIds = [];

    private bool $createdMenuCategory = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('B12 stock-equation concurrency proof requires the guarded disposable MySQL lane.');
        }

        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupCommittedFixtures();
        } finally {
            parent::tearDown();
        }
    }

    public function test_parallel_receive_consume_and_adjust_preserve_exact_stock_equation_and_replay_identity(): void
    {
        $now = now('UTC');
        $branchId = 1;
        $token = 'b12-'.bin2hex(random_bytes(4));
        $staffId = $this->fixtureIds['staff_id'] = $this->createUser(['role_name' => 'Staff']);
        DB::table('staff_branch_assignments')->insert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
            'is_primary' => 1,
            'assigned_at' => $now,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ingredientId = $this->fixtureIds['ingredient_id'] = $this->createIngredient([
            'code' => strtoupper($token).'-INGREDIENT',
            'name' => 'B12 MySQL Equation Ingredient',
            'unit_code' => 'g',
        ]);
        $this->createdMenuCategory = ! DB::table('menu_categories')->where('name', 'Test Food')->exists();
        $itemId = $this->fixtureIds['item_id'] = $this->createMenuItem(['code' => strtoupper($token).'-ITEM']);
        $this->fixtureIds['category_id'] = (int) DB::table('menu_items')->where('item_id', $itemId)->value('category_id');
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '80.000',
            'unit_code' => 'g',
            'sort_order' => 10,
        ]);

        $customerId = $this->fixtureIds['customer_id'] = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->fixtureIds['reservation_id'] = $this->createReservation([
            'user_id' => $customerId,
            'branch_id' => $branchId,
            'status' => 'Reserved',
        ]);
        $orderId = $this->fixtureIds['order_id'] = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $orderItemId = $this->fixtureIds['order_item_id'] = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '50000',
            'status' => 'Ordered',
        ]);

        $supplierId = $this->fixtureIds['supplier_id'] = (int) DB::table('suppliers')->insertGetId([
            'code' => strtoupper($token).'-SUPPLIER',
            'name' => 'B12 MySQL Equation Supplier',
            'is_active' => 1,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $purchaseOrderId = $this->fixtureIds['purchase_order_id'] = (int) DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supplierId,
            'branch_id' => $branchId,
            'order_code' => strtoupper($token).'-PO',
            'purchase_order_status' => 'Ordered',
            'ordered_at' => $now,
            'created_by' => $staffId,
            'updated_by' => $staffId,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $purchaseOrderLineId = $this->fixtureIds['purchase_order_line_id'] = (int) DB::table('purchase_order_lines')->insertGetId([
            'purchase_order_id' => $purchaseOrderId,
            'ingredient_id' => $ingredientId,
            'ordered_quantity' => '50.000',
            'received_quantity' => '0.000',
            'unit_code' => 'g',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '120.000',
            'unit_code' => 'g',
            'reference_type' => 'manual_count',
            'reference_id' => $token.'-opening',
        ]);

        DB::beginTransaction();
        Ingredient::query()->whereKey($ingredientId)->lockForUpdate()->firstOrFail();

        $env = $this->subprocessEnvironment();
        $runner = base_path('tests/Support/run_inventory_stock_equation_race.php');
        $processes = [
            $this->startProcess([
                PHP_BINARY,
                $runner,
                'receive',
                (string) $purchaseOrderId,
                (string) $purchaseOrderLineId,
                (string) $staffId,
                $token,
            ], $env),
            $this->startProcess([
                PHP_BINARY,
                $runner,
                'consume',
                (string) $reservationId,
                (string) $orderId,
                (string) $orderItemId,
                (string) $staffId,
            ], $env),
            $this->startProcess([
                PHP_BINARY,
                $runner,
                'adjust',
                (string) $ingredientId,
                (string) $branchId,
                (string) $staffId,
                $token,
            ], $env),
        ];

        try {
            usleep(750000);
        } finally {
            DB::commit();
        }

        $outcomes = array_map(function (array $running): array {
            $result = $this->finishProcess($running);
            self::assertSame('', trim($result['stderr']), 'Stock-equation subprocess wrote unexpected stderr output.');
            self::assertSame(0, $result['exit_code'], 'Stock-equation subprocess did not exit cleanly.');

            return json_decode(trim($result['stdout']), true, 512, JSON_THROW_ON_ERROR);
        }, $processes);

        self::assertCount(3, array_filter($outcomes, static fn (array $outcome): bool => $outcome['ok'] === true));
        self::assertSame(['adjust', 'consume', 'receive'], collect($outcomes)->pluck('action')->sort()->values()->all());

        $this->runReplay($runner, ['receive', (string) $purchaseOrderId, (string) $purchaseOrderLineId, (string) $staffId, $token], $env);
        $this->runReplay($runner, ['consume', (string) $reservationId, (string) $orderId, (string) $orderItemId, (string) $staffId], $env);

        self::assertSame('50.000', number_format((float) DB::table('purchase_order_lines')->where('po_line_id', $purchaseOrderLineId)->value('received_quantity'), 3, '.', ''));
        self::assertSame(1, (int) DB::table('ingredient_stock_movements')->where('ingredient_id', $ingredientId)->where('reference_type', 'PurchaseReceipt')->count());
        self::assertSame(1, (int) DB::table('ingredient_stock_movements')->where('ingredient_id', $ingredientId)->where('reference_type', 'ReservationOrderItemConsumption')->count());
        self::assertSame(1, (int) DB::table('ingredient_stock_movements')->where('ingredient_id', $ingredientId)->where('reference_type', 'B12MysqlAdjustment')->count());

        $opening = 120.000;
        $received = 50.000;
        $consumed = 80.000;
        $adjustedOut = 30.000;
        $expectedClosing = $opening + $received - $consumed - $adjustedOut;
        $actualClosing = (float) DB::table('ingredient_stock_movements')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity_delta');

        self::assertSame('60.000', number_format($expectedClosing, 3, '.', ''));
        self::assertSame(number_format($expectedClosing, 3, '.', ''), number_format($actualClosing, 3, '.', ''));
        self::assertGreaterThanOrEqual(0.0, $actualClosing);
    }

    private function cleanupCommittedFixtures(): void
    {
        if ($this->fixtureIds === [] || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::transaction(function (): void {
            $purchaseOrderId = $this->fixtureIds['purchase_order_id'] ?? 0;
            $ingredientId = $this->fixtureIds['ingredient_id'] ?? 0;
            $reservationId = $this->fixtureIds['reservation_id'] ?? 0;
            $itemId = $this->fixtureIds['item_id'] ?? 0;

            if ($purchaseOrderId > 0) {
                $receiptIds = DB::table('purchase_receipts')
                    ->where('purchase_order_id', $purchaseOrderId)
                    ->pluck('receipt_id');
                DB::table('purchase_receipt_lines')->whereIn('receipt_id', $receiptIds)->delete();
                DB::table('purchase_receipts')->whereIn('receipt_id', $receiptIds)->delete();
                DB::table('purchase_orders')->where('purchase_order_id', $purchaseOrderId)->delete();
            }

            if ($ingredientId > 0) {
                DB::table('ingredient_stock_movements')->where('ingredient_id', $ingredientId)->delete();
            }

            if ($reservationId > 0) {
                DB::table('reservations')->where('reservation_id', $reservationId)->delete();
            }

            if ($itemId > 0) {
                DB::table('menu_items')->where('item_id', $itemId)->delete();
            }

            if ($ingredientId > 0) {
                DB::table('ingredients')->where('ingredient_id', $ingredientId)->delete();
            }

            if (($this->fixtureIds['supplier_id'] ?? 0) > 0) {
                DB::table('suppliers')->where('supplier_id', $this->fixtureIds['supplier_id'])->delete();
            }

            DB::table('users')->whereIn('user_id', array_filter([
                $this->fixtureIds['staff_id'] ?? 0,
                $this->fixtureIds['customer_id'] ?? 0,
            ]))->delete();

            if ($this->createdMenuCategory && ($this->fixtureIds['category_id'] ?? 0) > 0) {
                DB::table('menu_categories')
                    ->where('category_id', $this->fixtureIds['category_id'])
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('menu_items')
                            ->whereColumn('menu_items.category_id', 'menu_categories.category_id');
                    })
                    ->delete();
            }
        });

        $this->fixtureIds = [];
    }

    /**
     * @param  list<string>  $args
     * @param  array<string,string>  $env
     */
    private function runReplay(string $runner, array $args, array $env): void
    {
        $result = $this->finishProcess($this->startProcess([PHP_BINARY, $runner, ...$args], $env));
        self::assertSame('', trim($result['stderr']));
        self::assertSame(0, $result['exit_code']);
        self::assertTrue((bool) json_decode(trim($result['stdout']), true, 512, JSON_THROW_ON_ERROR)['ok']);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string,string>  $env
     * @return array{process:resource,pipes:array<int,resource>}
     */
    private function startProcess(array $command, array $env): array
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path(), $env);
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param  array{process:resource,pipes:array<int,resource>}  $running
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    private function finishProcess(array $running): array
    {
        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        $deadline = microtime(true) + 30;

        while (true) {
            $stdout .= (string) stream_get_contents($running['pipes'][1]);
            $stderr .= (string) stream_get_contents($running['pipes'][2]);
            $status = proc_get_status($running['process']);
            if (! $status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }

            if (microtime(true) >= $deadline) {
                proc_terminate($running['process']);
                self::fail('B12 stock-equation subprocess exceeded its 30 second timeout.');
            }

            usleep(10000);
        }

        $stdout .= (string) stream_get_contents($running['pipes'][1]);
        $stderr .= (string) stream_get_contents($running['pipes'][2]);
        fclose($running['pipes'][1]);
        fclose($running['pipes'][2]);
        $closeCode = proc_close($running['process']);
        if ($exitCode === -1 && $closeCode !== -1) {
            $exitCode = $closeCode;
        }

        return ['exit_code' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * @return array<string,string>
     */
    private function subprocessEnvironment(): array
    {
        $raw = array_merge($_ENV, $_SERVER, [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ]);

        $env = [];
        foreach ($raw as $key => $value) {
            if (is_scalar($value)) {
                $env[(string) $key] = (string) $value;
            }
        }

        return $env;
    }
}
