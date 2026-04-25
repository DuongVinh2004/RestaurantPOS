<?php

use App\Http\Middleware\CustomerOrStaffMiddleware;
use App\Http\Middleware\ResolveCustomerAuthMiddleware;
use App\Support\ApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'reqid',
    'audit.request',
    ResolveCustomerAuthMiddleware::class,
    CustomerOrStaffMiddleware::class,
])->get('/user', function (Request $request) {
    $staffActorUserId = $request->attributes->get('staff_actor_user_id');
    if ($staffActorUserId) {
        return response()->json([
            'auth_mode' => 'staff',
            'user' => [
                'user_id' => (int) $staffActorUserId,
                'role_id' => $request->attributes->get('staff_actor_role_id'),
                'role_name' => $request->attributes->get('staff_actor_role_name'),
                'staff_auth_mode' => $request->attributes->get('staff_auth_mode'),
            ],
        ]);
    }

    if ($request->user()) {
        return response()->json([
            'auth_mode' => 'customer',
            'user' => [
                'user_id' => (int) $request->user()->user_id,
                'full_name' => (string) ($request->user()->full_name ?? ''),
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
                'role_id' => $request->user()->role_id,
                'current_tier_id' => $request->user()->current_tier_id,
            ],
        ]);
    }

    return ApiErrorResponse::authenticationRequired($request, 'Authentication is required.');
});

Route::prefix('v1')
    ->middleware([
        'reqid',
        'audit.request',
    ])
    ->group(function () {
        require __DIR__.'/api/auth.php';
        require __DIR__.'/api/ops_release.php';
        require __DIR__.'/api/customer_self_service.php';
        require __DIR__.'/api/staff_pos.php';
        require __DIR__.'/api/admin.php';
    });
