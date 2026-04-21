<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\RestaurantZoneManagementService;
use App\Modules\BranchScheduling\Http\Requests\Admin\ListRestaurantZonesRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\UpdateRestaurantZoneRequest;
use App\Modules\BranchScheduling\Http\Resources\Admin\RestaurantZoneResource;
use Illuminate\Http\JsonResponse;

class RestaurantZoneController extends Controller
{
    public function __construct(
        private readonly RestaurantZoneManagementService $zoneService,
    ) {}

    public function index(ListRestaurantZonesRequest $request): JsonResponse
    {
        $result = $this->zoneService->listZones([
            'include_deleted' => $request->boolean('include_deleted', false),
            'include_unzoned' => $request->has('include_unzoned')
                ? $request->boolean('include_unzoned')
                : true,
        ]);

        return response()->json([
            'data' => RestaurantZoneResource::collection(collect($result['zones']))->resolve(),
            'meta' => $result['meta'],
        ]);
    }

    public function rename(UpdateRestaurantZoneRequest $request): JsonResponse
    {
        $result = $this->zoneService->renameZone(
            fromZoneInput: $request->validated('from_zone'),
            toZoneInput: $request->validated('to_zone'),
        );

        return response()->json([
            'data' => $result,
            'meta' => [
                'action' => 'admin_restaurant_zone_renamed',
            ],
        ]);
    }
}
