<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\ListAdminRestaurantZonesRequest;
use App\Http\Requests\Admin\Restaurant\RenameAdminRestaurantZoneRequest;
use App\Http\Resources\Admin\AdminZoneResource;
use App\Services\Admin\Restaurant\AdminRestaurantZoneService;
use Illuminate\Http\JsonResponse;

class AdminRestaurantZoneController extends Controller
{
    public function __construct(
        private readonly AdminRestaurantZoneService $zoneService,
    ) {}

    public function index(ListAdminRestaurantZonesRequest $request): JsonResponse
    {
        $result = $this->zoneService->listZones([
            'include_deleted' => $request->boolean('include_deleted', false),
            'include_unzoned' => $request->has('include_unzoned')
                ? $request->boolean('include_unzoned')
                : true,
        ]);

        return response()->json([
            'data' => AdminZoneResource::collection(collect($result['zones']))->resolve(),
            'meta' => $result['meta'],
        ]);
    }

    public function rename(RenameAdminRestaurantZoneRequest $request): JsonResponse
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
