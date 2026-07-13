<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$username = 'uat.cashier';
$user = DB::table('users')->where('username', $username)->first();
$order = DB::table('reservation_orders')->where('order_id', 20)->first();

if (!$order) {
    echo "Order 20 not found!\n";
    exit(1);
}

echo "Simulating OrderSettlementWorkflow->checkout for user: {$username} and order ID: 20\n";
echo "Using row_version from DB: {$order->row_version}\n";

try {
    $workflow = app(OrderSettlementWorkflow::class);
    $result = $workflow->checkout(
        orderId: 20,
        paymentMethod: 'cash',
        paidAmount: 0.0, // Assuming full amount since it's just a test
        currency: 'VND',
        transactionCode: '',
        paymentProvider: '',
        notes: 'Simulated checkout',
        discountAmount: null,
        expectedRowVersion: $order->row_version,
        staffUserId: $user->user_id,
        idempotencyKey: 'simulated-key-' . time()
    );
    echo "SUCCESS!\n";
} catch (ValidationException $e) {
    echo "FAILED with ValidationException:\n";
    echo json_encode($e->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable $t) {
    echo "FAILED with general error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
}
