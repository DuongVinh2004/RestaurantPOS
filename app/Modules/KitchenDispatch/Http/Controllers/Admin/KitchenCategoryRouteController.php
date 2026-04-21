<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\KitchenDispatch\Http\Requests\Admin\AssignKitchenCategoryRouteRequest;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationCategoryRouteResource;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationResource;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenCategoryRouteController extends Controller
{
    public function __construct(
        private readonly KitchenRoutingService $kitchenRoutingService,
    ) {}

    public function index(int $station_id, Request $request): JsonResponse
    {
        try {
            $result = $this->kitchenRoutingService->getStationRoutes($station_id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request);
        }

        return response()->json([
            'data' => KitchenStationCategoryRouteResource::collection($result['routes'])->toArray($request),
            'meta' => [
                'station' => (new KitchenStationResource($result['station']))->toArray($request),
                'count' => $result['routes']->count(),
            ],
        ]);
    }

    public function update(int $station_id, AssignKitchenCategoryRouteRequest $request): JsonResponse
    {
        try {
            $result = $this->kitchenRoutingService->syncStationRoutes($station_id, (array) $request->validated('routes', []));
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Kitchen station or category not found.');
        }

        return response()->json([
            'data' => KitchenStationCategoryRouteResource::collection($result['routes'])->toArray($request),
            'meta' => [
                'station' => (new KitchenStationResource($result['station']))->toArray($request),
                'count' => $result['routes']->count(),
            ],
        ]);
    }

    private function notFoundResponse(Request $request, string $message = 'Kitchen station not found.'): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            $message,
        );
    }
}
