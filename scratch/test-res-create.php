<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Reservations\Application\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

DB::table('reservation_tables')->delete();
DB::table('reservations')->delete();
DB::table('table_hold_details')->delete();
DB::table('table_holds')->delete();

// Find an active customer user
$customer = DB::table('users')->where('role_id', 3)->first();
// Find some tables at branch 5
$tables = DB::table('restaurant_tables')->where('branch_id', 5)->take(2)->get();
$tableIds = $tables->pluck('table_id')->toArray();

// Create a table hold
$sessionId = 'test-session-' . uniqid();
$from = Carbon::now('UTC')->addHours(2);
$to = Carbon::now('UTC')->addHours(3);

echo "Creating table hold...\n";
$holdId = (string) Str::uuid();
DB::table('table_holds')->insert([
    'hold_id' => $holdId,
    'session_id' => $sessionId,
    'user_id' => $customer->user_id,
    'branch_id' => 5,
    'start_time' => $from,
    'end_time' => $to,
    'duration_minutes' => 60,
    'hold_status' => 'Holding',
    'created_at' => Carbon::now('UTC'),
    'updated_at' => Carbon::now('UTC'),
    'expire_at' => Carbon::now('UTC')->addMinutes(5),
    'row_version' => 1,
]);

foreach ($tableIds as $tableId) {
    DB::table('table_hold_details')->insert([
        'hold_id' => $holdId,
        'table_id' => $tableId,
    ]);
}

$payload = [
    'hold_id' => $holdId,
    'session_id' => $sessionId,
    'start_time' => $from->toIso8601String(),
    'end_time' => $to->toIso8601String(),
    'guest_count' => 5,
    'user_id' => $customer->user_id,
    'table_ids' => $tableIds,
];

echo "Invoking createReservation with guest_count = 5...\n";
$service = app(ReservationService::class);
$reservation = $service->createReservation($payload, $customer->user_id);

echo "Created reservation details:\n";
echo "Source: " . $reservation->source . "\n";
echo "Guest count: " . $reservation->guest_count . "\n";
echo "Deposit required amount: " . $reservation->deposit_required_amount . "\n";
echo "Deposit status: " . $reservation->deposit_status->value . "\n";
