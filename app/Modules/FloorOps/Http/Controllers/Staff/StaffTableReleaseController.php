<?php

declare(strict_types=1);

namespace App\Modules\FloorOps\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Http\Resources\RestaurantTableResource;
use App\Modules\FloorOps\Application\Services\StaffTableReleaseService;
use App\Modules\FloorOps\Http\Requests\ReleaseTableRequest;
use Illuminate\Http\JsonResponse;

class StaffTableReleaseController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffTableReleaseService $releaseService,
    ) {
    }

    public function store(int $table_id, ReleaseTableRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $table = $this->releaseService->release(
            tableId: $table_id,
            staffUserId: $staffUserId,
            force: (bool) ($request->boolean('force') ?? false),
            notes: $request->input('notes'),
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
        );

        return response()->json([
            'data' => new RestaurantTableResource($table),
        ]);
    }
}
