<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $result = app(\App\Modules\BranchScheduling\Http\Controllers\Guest\RestaurantProfileController::class)->show(request())->getData(true);
    echo json_encode($result);
} catch (\Exception $e) {
    echo get_class($e) . ': ' . $e->getMessage();
}
