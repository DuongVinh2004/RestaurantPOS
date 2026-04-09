<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AdvanceWaitingListRequest;
use App\Http\Requests\Staff\CancelWaitingListRequest;
use App\Http\Requests\Staff\ListWaitingListRequest;
use App\Http\Requests\Staff\NotifyWaitingListRequest;
use App\Http\Requests\Staff\SeatWaitingListRequest;
use App\Http\Requests\Staff\StoreWaitingListRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\WaitingListResource;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Services\Staff\StaffWaitingListService;
use App\Services\WaitingList\WaitingListOperationalOrchestrationService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;

class StaffWaitingListController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffWaitingListService $waitingListService,
        private readonly WaitingListOperationalOrchestrationService $orchestrationService,
    ) {}

    public function index(ListWaitingListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $summaryEntries = $this->orchestrationService->hydrateCollection($this->waitingListService->listQueue($validated));
        $extraMeta = [
            'summary' => $this->orchestrationService->buildSummary($summaryEntries),
            'realtime' => app(StaffOperationalRealtimeService::class)->describeTopic(
                StaffOperationalRealtimeService::TOPIC_WAITING_LIST,
                '/api/v1/staff/waiting-list/changes',
                ['waiting_list'],
            ),
        ];

        if ($request->wantsListingPagination()) {
            $paginator = $this->waitingListService->paginateQueue($validated);
            $entries = $this->orchestrationService->hydrateCollection($paginator->getCollection());
            $paginator->setCollection($entries);

            return response()->json([
                'data' => WaitingListResource::collection($entries),
                'meta' => ListingMetaFactory::paginated(
                    $paginator,
                    [
                        'status' => $validated['status'] ?? null,
                        'active_only' => (bool) ($validated['active_only'] ?? true),
                        'phone' => $validated['phone'] ?? null,
                        'guest_name' => $validated['guest_name'] ?? null,
                        'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                    ],
                    [
                        'supported' => true,
                        'value' => (string) ($validated['sort'] ?? '-priority'),
                        'by' => (string) ($validated['sort_by'] ?? 'priority'),
                        'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                    ],
                    ListingMetaFactory::contract(
                        ['status', 'active_only', 'phone', 'guest_name', 'branch_id'],
                        ['priority', 'requested_at', 'notified_at', 'guest_name', 'guest_count', 'waiting_id'],
                        '-priority',
                        true,
                        100,
                        [
                            'status' => 'filter[status]',
                            'active_only' => 'filter[active_only]',
                            'phone' => 'filter[phone]',
                            'guest_name' => 'filter[guest_name]',
                            'branch_id' => 'filter[branch_id]',
                            'sort_by' => 'sort',
                            'sort_dir' => 'sort',
                        ],
                    ),
                    $extraMeta,
                ),
            ]);
        }

        $entries = $summaryEntries;

        return response()->json([
            'data' => WaitingListResource::collection($entries),
            'meta' => ListingMetaFactory::legacyCollection(
                $entries->count(),
                [
                    'status' => $validated['status'] ?? null,
                    'active_only' => (bool) ($validated['active_only'] ?? true),
                    'phone' => $validated['phone'] ?? null,
                    'guest_name' => $validated['guest_name'] ?? null,
                    'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-priority'),
                    'by' => (string) ($validated['sort_by'] ?? 'priority'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    ['status', 'active_only', 'phone', 'guest_name', 'branch_id'],
                    ['priority', 'requested_at', 'notified_at', 'guest_name', 'guest_count', 'waiting_id'],
                    '-priority',
                    true,
                    100,
                    [
                        'status' => 'filter[status]',
                        'active_only' => 'filter[active_only]',
                        'phone' => 'filter[phone]',
                        'guest_name' => 'filter[guest_name]',
                        'branch_id' => 'filter[branch_id]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
                $extraMeta,
            ),
        ]);
    }

    public function store(StoreWaitingListRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $entry = $this->waitingListService->createEntry($request->validated(), $staffUserId);

        return response()->json([
            'data' => new WaitingListResource($this->orchestrationService->hydrateEntry($entry->load('user'))),
        ], 201);
    }

    public function notify(int $id, NotifyWaitingListRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $entry = $this->waitingListService->notifyEntry(
            $id,
            $request->validated(),
            $staffUserId,
            (int) $request->input('row_version'),
        );

        return response()->json([
            'data' => new WaitingListResource($this->orchestrationService->hydrateEntry($entry->load('user'))),
        ]);
    }

    public function seat(int $id, SeatWaitingListRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->waitingListService->seatEntry(
            $id,
            $request->validated(),
            $staffUserId,
            (int) $request->input('row_version'),
        );

        return response()->json([
            'data' => [
                'waiting_list' => new WaitingListResource($this->orchestrationService->hydrateEntry($result['waiting_list']->load('user'))),
                'reservation' => new ReservationResource($result['reservation']->load(['tables', 'user', 'orders.items.item', 'payments'])),
            ],
        ]);
    }

    public function cancel(int $id, CancelWaitingListRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $entry = $this->waitingListService->cancelEntry(
            $id,
            (string) ($request->input('cancel_reason') ?? ''),
            $staffUserId,
            (int) $request->input('row_version'),
        );

        return response()->json([
            'data' => new WaitingListResource($this->orchestrationService->hydrateEntry($entry->load('user'))),
        ]);
    }

    public function advance(int $id, AdvanceWaitingListRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->orchestrationService->advanceQueueAfterResponse(
            waitingId: $id,
            staffUserId: $staffUserId,
            expectedRowVersion: (int) $request->input('row_version'),
            holdMinutesOverride: $request->filled('hold_minutes') ? (int) $request->input('hold_minutes') : null,
        );

        return response()->json([
            'data' => [
                'source_waiting_list' => new WaitingListResource($result['source_waiting_list']),
                'advanced_waiting_list' => $result['advanced_waiting_list'] ? new WaitingListResource($result['advanced_waiting_list']) : null,
                'automation' => $result['automation'],
            ],
        ]);
    }
}
