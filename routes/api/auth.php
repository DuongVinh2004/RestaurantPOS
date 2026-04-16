<?php

use App\Http\Controllers\Api\Auth\CustomerAuthController;
use App\Http\Controllers\Api\Auth\StaffAuthController;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Http\Middleware\StaffApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::prefix('customer')->group(function () {
        Route::post('login', [CustomerAuthController::class, 'login'])
            ->middleware('throttle:'.
                max(1, (int) config('customer_auth.login_throttle_limit', 10)).','.
                max(1, (int) ceil(((int) config('customer_auth.login_throttle_window_seconds', 60)) / 60))
            );

        Route::middleware([ResolveCustomerAuthMiddleware::class])->group(function () {
            Route::get('me', [CustomerAuthController::class, 'me']);
            Route::post('refresh', [CustomerAuthController::class, 'refresh']);
            Route::post('logout', [CustomerAuthController::class, 'logout']);
        });
    });

    Route::prefix('staff')->group(function () {
        Route::post('login', [StaffAuthController::class, 'login'])
            ->middleware('throttle:'.
                max(1, (int) config('staff_auth.login_throttle_limit', 10)).','.
                max(1, (int) ceil(((int) config('staff_auth.login_throttle_window_seconds', 60)) / 60))
            );

        Route::middleware([StaffApiKeyMiddleware::class])->group(function () {
            Route::get('me', [StaffAuthController::class, 'me']);
            Route::post('refresh', [StaffAuthController::class, 'refresh']);
            Route::post('logout', [StaffAuthController::class, 'logout']);
        });
    });
});
