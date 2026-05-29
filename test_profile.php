<?php

use App\Modules\BranchScheduling\Http\Controllers\Guest\RestaurantProfileController;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $result = app(RestaurantProfileController::class)->show(request())->getData(true);
    echo json_encode($result);
} catch (Exception $e) {
    echo get_class($e).': '.$e->getMessage();
}
