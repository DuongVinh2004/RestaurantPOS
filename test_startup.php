<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\StaffStartupContextBuilder;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::where('username', 'bootstrap-admin')->first();
$builder = app(StaffStartupContextBuilder::class);
$cap = $builder->buildCapabilityContext($user);
echo json_encode($builder->buildStartupContext($user, $cap), JSON_PRETTY_PRINT);
