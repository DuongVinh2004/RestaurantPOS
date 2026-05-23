<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$res = DB::table('reservations')->orderByDesc('reservation_id')->first();
print_r($res);
