<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithCustomerReservationNotFound
{
    protected function notFoundReservationResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            'Reservation not found.',
        );
    }
}
