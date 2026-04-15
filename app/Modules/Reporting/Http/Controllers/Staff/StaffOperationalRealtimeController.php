<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Services\StaffOperationalRealtimeService;
use App\Modules\Reporting\Http\Requests\Staff\ListOperationalRealtimeChangesRequest;
use Illuminate\Http\JsonResponse;

class StaffOperationalRealtimeController extends Controller
{
    public function __construct(
        private readonly StaffOperationalRealtimeService $realtimeService,
    ) {}

    public function boardChanges(ListOperationalRealtimeChangesRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtimeService->readTopic(
                StaffOperationalRealtimeService::TOPIC_BOARD,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
            ),
        ]);
    }

    public function waitingListChanges(ListOperationalRealtimeChangesRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtimeService->readTopic(
                StaffOperationalRealtimeService::TOPIC_WAITING_LIST,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
            ),
        ]);
    }
}
