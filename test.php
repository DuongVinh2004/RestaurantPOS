<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = Illuminate\Support\Facades\Http::post('http://localhost:8000/api/v1/auth/staff/login', [
    'identifier' => 'host01',
    'password' => 'password',
    'device_name' => 'Máy phục vụ',
    'session_transport' => 'refresh_cookie'
]);
echo $response->status() . PHP_EOL . $response->body();
