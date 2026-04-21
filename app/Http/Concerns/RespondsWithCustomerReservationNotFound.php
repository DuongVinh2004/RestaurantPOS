<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithCustomerReservationNotFound
{
    protected function notFoundReservationResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::notFound($request, 'Reservation not found.');
    }
}
