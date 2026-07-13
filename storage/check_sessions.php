<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sessions = DB::table('staff_access_sessions')
    ->join('users', 'staff_access_sessions.user_id', '=', 'users.user_id')
    ->select('staff_access_sessions.*', 'users.username')
    ->get();

echo "ACTIVE SESSIONS:\n";
foreach ($sessions as $s) {
    echo sprintf(
        "Session ID: %s | User: %s (ID %d) | Expires: %s | Revoked: %s\n",
        $s->session_id ?? 'NULL',
        $s->username,
        $s->user_id,
        $s->expires_at,
        $s->revoked_at ?? 'NULL'
    );
}
