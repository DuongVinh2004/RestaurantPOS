<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\UseCases\Previews\CustomerReservationOrderBillService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Ordering\Http\Resources\ReservationOrderResource;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrBillPreviewController extends Controller
{
    public function __construct(
        private readonly CustomerReservationOrderBillService $orderBillService,
    ) {}

    public function show(string $token, Request $request): JsonResponse
    {
        // Find the table by QR token
        $table = RestaurantTable::where('qr_payment_token', $token)->first();

        if (! $table) {
            return ApiErrorResponse::notFound($request, 'Invalid QR token.');
        }

        // Find an active reservation for this table
        // Active means status is Arrived/Seated/Reserved (in POS terms usually Confirmed/Reserved but past start time)
        // Wait, looking at reservations schema: status IN ('Confirmed', 'Reserved')
        // and current time overlaps with reservation window or it's currently occupied.
        // Let's find any reservation that is currently linked to this table via reservation_tables
        // and is currently in 'Confirmed' or 'Reserved' status.
        $reservationTable = ReservationTable::query()
            ->where('table_id', $table->table_id)
            ->whereHas('reservation', function ($query) {
                $query->whereIn('status', ['Confirmed', 'Reserved']);
            })
            ->with('reservation')
            ->first();

        if (! $reservationTable || ! $reservationTable->reservation) {
            return response()->json([
                'data' => [
                    'table' => [
                        'table_id' => $table->table_id,
                        'table_code' => $table->table_code,
                        'zone' => $table->zone,
                    ],
                    'reservation_id' => null,
                    'active_order' => null,
                    'bill_preview' => null,
                ],
                'meta' => [
                    'has_active_session' => false,
                ],
            ]);
        }

        $reservation = $reservationTable->reservation;
        $result = $this->orderBillService->previewAccessibleBill($reservation);

        return response()->json([
            'data' => [
                'table' => [
                    'table_id' => $table->table_id,
                    'table_code' => $table->table_code,
                    'zone' => $table->zone,
                ],
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'active_order' => $result['active_order'] !== null
                    ? (new ReservationOrderResource($result['active_order']))->toArray($request)
                    : null,
                'bill_preview' => $result['bill_preview'],
            ],
            'meta' => [
                'has_active_session' => true,
            ],
        ]);
    }
}
