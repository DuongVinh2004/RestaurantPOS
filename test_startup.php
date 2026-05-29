<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Modules\IdentityAccess\Domain\Models\User::where('username', 'bootstrap-admin')->first();
$builder = app(App\Modules\IdentityAccess\Infrastructure\Internal\StaffStartupContextBuilder::class);
$cap = $builder->buildCapabilityContext($user);
echo json_encode($builder->buildStartupContext($user, $cap), JSON_PRETTY_PRINT);
