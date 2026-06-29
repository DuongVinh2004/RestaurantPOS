<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$u = App\Modules\IdentityAccess\Domain\Models\User::where('username', 'realadmin')->first();
$u->password_hash = Illuminate\Support\Facades\Hash::make('password123');
$u->save();
echo "Updated password for realadmin to password123\n";
