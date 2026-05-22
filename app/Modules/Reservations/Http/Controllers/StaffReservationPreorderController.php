<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reservations\Application\Services\StaffReservationPreorderService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReservationPreorderController extends Controller
{
    public function __construct(
        private readonly StaffReservationPreorderService $service,
    ) {}

    public function show(int $id, Request $request): JsonResponse
    {
        try {
            // Using CustomerReservationPreorderService for reading is fine, or we can use Staff service.
            // Let's use StaffReservationPreorderService for reading.
            $result = $this->service->getPreorderForStaff($id);
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::notFound($request, 'Reservation not found.');
        }

        return response()->json([
            'data' => [
                'reservation_id' => $result['reservation_id'],
                'pre_order' => $result['pre_order'],
            ],
            'meta' => [
                'action' => 'staff_reservation_preorder_show',
            ],
        ]);
    }

    public function confirm(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->confirmPreorder($id, (int) $request->user()->user_id);
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::notFound($request, 'Reservation not found.');
        }

        return response()->json([
            'data' => [
                'preorder_id' => $result->preorder_id,
                'status' => $result->status->value,
                'confirmed_at' => $result->confirmed_at?->toIso8601String(),
            ],
            'meta' => [
                'action' => 'staff_reservation_preorder_confirm',
            ],
        ]);
    }

    public function reject(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->rejectPreorder($id, (int) $request->user()->user_id);
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::notFound($request, 'Reservation not found.');
        }

        return response()->json([
            'data' => [
                'preorder_id' => $result->preorder_id,
                'status' => $result->status->value,
                'rejected_at' => $result->rejected_at?->toIso8601String(),
            ],
            'meta' => [
                'action' => 'staff_reservation_preorder_reject',
            ],
        ]);
    }

    public function convert(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->service->convertPreorder($id, (int) $request->user()->user_id);
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::notFound($request, 'Reservation not found.');
        }

        return response()->json([
            'data' => [
                'order_id' => $result->order_id,
                'order_type' => $result->order_type->value,
                'status' => $result->status->value,
            ],
            'meta' => [
                'action' => 'staff_reservation_preorder_convert',
            ],
        ]);
    }
}
