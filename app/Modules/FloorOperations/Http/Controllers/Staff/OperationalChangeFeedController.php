<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Platform\Realtime\Http\Requests\ListOperationalChangeFeedRequest;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use Illuminate\Http\JsonResponse;

class OperationalChangeFeedController extends Controller
{
    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
    ) {}

    public function boardChanges(ListOperationalChangeFeedRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtimeService->readTopic(
                OperationalRealtimeService::TOPIC_BOARD,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
            ),
        ]);
    }

    public function waitingListChanges(ListOperationalChangeFeedRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtimeService->readTopic(
                OperationalRealtimeService::TOPIC_WAITING_LIST,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
            ),
        ]);
    }
}
