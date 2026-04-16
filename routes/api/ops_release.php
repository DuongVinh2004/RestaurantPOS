<?php

use App\Http\Middleware\MetricsRequestMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Modules\CheckoutPayments\Http\Controllers\PaymentProviderWebhookController;
use App\Platform\Health\Http\HealthController;
use App\Platform\Metrics\Http\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'health']);

Route::middleware([StaffApiKeyMiddleware::class])->group(function (): void {
    Route::get('health/detailed', [HealthController::class, 'detailed'])
        ->middleware('staff.capability:ops.health.view');
    Route::get('health/redis', [HealthController::class, 'redis'])
        ->middleware('staff.capability:ops.health.view');
    Route::get('metrics', [MetricsController::class, 'index'])
        ->middleware('staff.capability:ops.metrics.view');
});

Route::middleware([
    MetricsRequestMiddleware::class,
    ResolveCustomerAuthMiddleware::class,
])->group(function () {
    Route::post('payments/providers/{provider_code}/webhooks', [PaymentProviderWebhookController::class, 'handle']);
});
