<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\AppliesDeprecatedRouteHeaders;
use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Application\Queries\StaffTableBoardService;
use App\Modules\FloorOperations\Http\Requests\Staff\TableBoardRequest;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableBoardController extends Controller
{
    use AppliesDeprecatedRouteHeaders;
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffTableBoardService $boardService,
        private readonly OperationalRealtimeService $realtimeService,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function legacyIndex(TableBoardRequest $request): JsonResponse
    {
        $response = $this->index($request);
        $payload = $response->getData(true);
        $payload['meta'] = array_merge((array) ($payload['meta'] ?? []), [
            'request_route' => '/'.trim((string) ($request->route()?->uri() ?? $request->path()), '/'),
            'canonical_route' => '/api/v1/staff/tables/board',
        ]);
        $response->setData($payload);

        return $this->markDeprecatedRouteAlias(
            $response,
            '/api/v1/staff/table-board',
            '/api/v1/staff/tables/board',
        );
    }

    public function index(TableBoardRequest $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;
        $staffActorUserId = $this->resolveStaffActorUserId($request);

        try {
            $accessibleBranchIds = $this->branchContextService->branchScopeOrAccessible(
                $staffActorUserId,
                $branchId,
                (int) $request->attributes->get('staff_actor_role_id', 0) ?: null,
                trim((string) $request->attributes->get('staff_actor_role_name', '')) ?: null,
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        $snapshot = $this->boardService->buildBoardSnapshot(
            from: $request->date('from'),
            to: $request->date('to'),
            branchId: $branchId,
            zone: $request->input('zone'),
            includeHolds: $request->boolean('include_holds', true),
            accessibleBranchIds: $accessibleBranchIds,
        );

        return response()->json(array_merge($snapshot, [
            'meta' => ListingMetaFactory::collection(
                [
                    'from' => $request->date('from')?->toIso8601String(),
                    'to' => $request->date('to')?->toIso8601String(),
                    'branch_id' => $request->integer('branch_id') ?: null,
                    'zone' => $request->input('zone'),
                    'include_holds' => $request->boolean('include_holds', true),
                    'group_by' => $request->input('group_by'),
                ],
                [
                    'supported' => false,
                    'value' => null,
                    'by' => null,
                    'dir' => null,
                ],
                ListingMetaFactory::contract(
                    ['date', 'from', 'to', 'branch_id', 'zone', 'include_holds', 'group_by'],
                    [],
                    null,
                    false,
                    0,
                    [
                        'date' => 'filter[date]',
                        'from' => 'filter[from]',
                        'to' => 'filter[to]',
                        'branch_id' => 'filter[branch_id]',
                        'zone' => 'filter[zone]',
                        'include_holds' => 'filter[include_holds]',
                        'group_by' => 'filter[group_by]',
                    ],
                ),
                [
                    'action' => 'staff_table_board',
                    'supported_actions' => [
                        'start_walk_in_session' => [
                            'endpoint_template' => '/api/v1/staff/service-sessions/walk-in',
                        ],
                        'check_in' => [
                            'endpoint_template' => '/api/v1/staff/reservations/{reservation_id}/check-in',
                        ],
                        'move_table' => [
                            'endpoint_template' => '/api/v1/staff/reservations/{reservation_id}/move-table',
                        ],
                        'assign_suggested_table' => [
                            'endpoint_template' => '/api/v1/staff/reservations/{reservation_id}/assign-table',
                        ],
                        'assign_best_fit' => [
                            'endpoint_template' => '/api/v1/staff/reservations/{reservation_id}/assign-best-fit',
                        ],
                    ],
                    'realtime' => $this->realtimeService->describeTopic(
                        OperationalRealtimeService::TOPIC_BOARD,
                        '/api/v1/staff/tables/board/changes',
                        ['board', 'timeline'],
                    ),
                ],
            ),
        ]));
    }

    private function notFoundResponse(Request $request, string $message): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            $message,
        );
    }
}
