<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Queries\Analytics\GetAnalyticsOverviewHandler;
use App\Modules\Reporting\Http\Requests\Staff\AnalyticsOverviewRequest;
use Illuminate\Http\JsonResponse;

class AnalyticsOverviewController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly GetAnalyticsOverviewHandler $getAnalyticsOverviewHandler,
    ) {}

    public function index(AnalyticsOverviewRequest $request): JsonResponse
    {
        $filters = $request->validated();
        
        $data = $this->getAnalyticsOverviewHandler->handle(
            $filters,
            $this->resolveStaffActorUserId($request),
            (int) $request->attributes->get('staff_actor_role_id', 0) ?: null,
            trim((string) $request->attributes->get('staff_actor_role_name', '')) ?: null,
        );

        return response()->json(['data' => $data]);
    }
}
