<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Requests\Staff\ListStaffReservationsRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\StaffReservationInboxResource;
use App\Services\Staff\StaffReservationInboxService;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use App\Support\ReservationAccessScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReservationInboxController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffReservationInboxService $inboxService,
    ) {}

    public function index(ListStaffReservationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $paginator = $this->inboxService->paginate($validated, $this->resolveStaffActorUserId($request));
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::json(
                $request,
                404,
                'not_found',
                'Branch not found.',
            );
        }

        return response()->json([
            'data' => StaffReservationInboxResource::collection($paginator->getCollection()),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'bucket' => (string) ($validated['bucket'] ?? 'upcoming'),
                    'status' => $validated['status'] ?? null,
                    'reservation_code' => $validated['reservation_code'] ?? null,
                    'source' => $validated['source'] ?? null,
                    'q' => $validated['q'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'deposit_acknowledged' => array_key_exists('deposit_acknowledged', $validated) ? $validated['deposit_acknowledged'] : null,
                    'deposit_intent_status' => $validated['deposit_intent_status'] ?? null,
                    'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                    'table_id' => isset($validated['table_id']) ? (int) $validated['table_id'] : null,
                    'start_from' => $validated['start_from'] ?? null,
                    'start_to' => $validated['start_to'] ?? null,
                    'include_financials' => (bool) ($validated['include_financials'] ?? false),
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? 'start_time'),
                    'by' => (string) ($validated['sort_by'] ?? 'start_time'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                ],
                ListingMetaFactory::contract(
                    [
                        'bucket',
                        'status',
                        'reservation_code',
                        'source',
                        'q',
                        'phone',
                        'deposit_acknowledged',
                        'deposit_intent_status',
                        'user_id',
                        'table_id',
                        'start_from',
                        'start_to',
                        'include_financials',
                    ],
                    ['start_time', 'end_time', 'created_at', 'updated_at', 'reservation_id', 'guest_count'],
                    (string) (($validated['bucket'] ?? 'upcoming') === 'history' ? '-start_time' : 'start_time'),
                    true,
                    100,
                    [
                        'bucket' => 'filter[bucket]',
                        'status' => 'filter[status]',
                        'reservation_code' => 'filter[reservation_code]',
                        'source' => 'filter[source]',
                        'q' => 'filter[q]',
                        'phone' => 'filter[phone]',
                        'deposit_acknowledged' => 'filter[deposit_acknowledged]',
                        'deposit_intent_status' => 'filter[deposit_intent_status]',
                        'user_id' => 'filter[user_id]',
                        'table_id' => 'filter[table_id]',
                        'start_from' => 'filter[start_from]',
                        'start_to' => 'filter[start_to]',
                        'include_financials' => 'filter[include_financials]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    public function show(int $reservation_id, Request $request): JsonResponse
    {
        try {
            $reservation = $this->inboxService->findForStaffOrFail(
                $reservation_id,
                $this->resolveStaffActorUserId($request),
            );
        } catch (ModelNotFoundException) {
            return ApiErrorResponse::json(
                $request,
                404,
                'not_found',
                'Reservation not found.',
            );
        }

        $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

        return response()->json([
            'data' => (new ReservationResource($reservation))->toArray($request),
        ]);
    }
}
