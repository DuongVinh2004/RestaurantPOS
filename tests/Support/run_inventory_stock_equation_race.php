<?php

declare(strict_types=1);

use App\Enums\ReservationOrderItemStatus;
use App\Modules\InventoryProcurement\Application\UseCases\Inventory\InventoryStockMovementService;
use App\Modules\InventoryProcurement\Application\UseCases\Inventory\OrderItemInventoryConsumptionService;
use App\Modules\InventoryProcurement\Application\UseCases\Procurement\ProcurementManagementService;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = trim((string) ($argv[1] ?? ''));

try {
    if ($action === 'receive') {
        $purchaseOrderId = (int) ($argv[2] ?? 0);
        $purchaseOrderLineId = (int) ($argv[3] ?? 0);
        $staffId = (int) ($argv[4] ?? 0);
        $token = trim((string) ($argv[5] ?? ''));
        $app->make(ProcurementManagementService::class)->createReceipt($purchaseOrderId, [
            'receipt_code' => strtoupper($token).'-RECEIPT',
            'supplier_document_no' => strtoupper($token).'-DOC',
            'notes' => 'B12 concurrent stock equation receipt',
            'lines' => [[
                'purchase_order_line_id' => $purchaseOrderLineId,
                'received_quantity' => '50.000',
                'unit_code' => 'g',
            ]],
        ], $staffId);
    } elseif ($action === 'consume') {
        $reservation = Reservation::query()->findOrFail((int) ($argv[2] ?? 0));
        $order = ReservationOrder::query()->findOrFail((int) ($argv[3] ?? 0));
        $orderItem = ReservationOrderItem::query()->findOrFail((int) ($argv[4] ?? 0));
        $staffId = (int) ($argv[5] ?? 0);
        $app->make(OrderItemInventoryConsumptionService::class)->syncInventoryForStatusChange(
            reservation: $reservation,
            order: $order,
            item: $orderItem,
            previousStatus: ReservationOrderItemStatus::Ordered,
            targetStatus: ReservationOrderItemStatus::Served,
            actorUserId: $staffId,
        );
    } elseif ($action === 'adjust') {
        $ingredientId = (int) ($argv[2] ?? 0);
        $branchId = (int) ($argv[3] ?? 0);
        $staffId = (int) ($argv[4] ?? 0);
        $token = trim((string) ($argv[5] ?? ''));
        $app->make(InventoryStockMovementService::class)->recordMovement($ingredientId, [
            'branch_id' => $branchId,
            'movement_type' => IngredientStockMovement::TYPE_ADJUSTMENT_DECREASE,
            'quantity' => '30.000',
            'unit_code' => 'g',
            'reference_type' => 'B12MysqlAdjustment',
            'reference_id' => $token.'-adjustment',
            'notes' => 'B12 concurrent stock equation adjustment',
        ], $staffId);
    } else {
        throw new InvalidArgumentException('Unsupported stock-equation race action.');
    }

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'action' => $action,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'action' => $action,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(2);
}
