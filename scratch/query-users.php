<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$users = DB::table('users')->select('user_id', 'username', 'role_id')->get()->toArray();
print_r($users);
