<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$maintenance = app(\App\Platform\Health\Services\BookingMaintenanceService::class);
echo "Calling expireHolds...\n";
$expiredCount = $maintenance->expireHolds();
echo "Expired count: $expiredCount\n";

$holds = DB::table('table_holds')
    ->whereNull('confirmed_reservation_id')
    ->whereNotNull('session_id')
    ->where('session_id', '<>', '')
    ->whereIn('hold_status', ['Confirmed', 'Holding', 'Pending'])
    ->get()
    ->toArray();

echo "DB Time: " . DB::select("SELECT NOW() as now")[0]->now . "\n";
echo "Current holds count: " . count($holds) . "\n";
print_r($holds);

if (count($holds) > 0) {
    echo "Force updating holds to 'Expired'...\n";
    $affected = DB::table('table_holds')
        ->whereNull('confirmed_reservation_id')
        ->whereNotNull('session_id')
        ->where('session_id', '<>', '')
        ->whereIn('hold_status', ['Confirmed', 'Holding', 'Pending'])
        ->update(['hold_status' => 'Expired']);
    echo "Force expired: $affected rows\n";
}

