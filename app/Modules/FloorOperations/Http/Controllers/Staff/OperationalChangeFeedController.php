<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Platform\Realtime\Http\Requests\ListOperationalChangeFeedRequest;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalChangeFeedController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function boardChanges(ListOperationalChangeFeedRequest $request): JsonResponse
    {
        try {
            $accessibleBranchIds = $this->branchScopeForRequest($request);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        return response()->json([
            'data' => $this->realtimeService->readTopic(
                OperationalRealtimeService::TOPIC_BOARD,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
                $accessibleBranchIds,
            ),
        ]);
    }

    public function waitingListChanges(ListOperationalChangeFeedRequest $request): JsonResponse
    {
        try {
            $accessibleBranchIds = $this->branchScopeForRequest($request);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        return response()->json([
            'data' => $this->realtimeService->readTopic(
                OperationalRealtimeService::TOPIC_WAITING_LIST,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
                $accessibleBranchIds,
            ),
        ]);
    }

    /**
     * @return list<int>
     */
    private function branchScopeForRequest(ListOperationalChangeFeedRequest $request): array
    {
        return $this->branchContextService->branchScopeOrAccessible(
            $this->resolveStaffActorUserId($request),
            $request->integer('branch_id') ?: null,
        );
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
