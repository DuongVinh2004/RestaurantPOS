<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\KitchenDispatch\Application\Services\KitchenRoutingService;
use App\Modules\KitchenDispatch\Http\Requests\CreateAdminKitchenStationRequest;
use App\Modules\KitchenDispatch\Http\Requests\SyncAdminKitchenStationRoutesRequest;
use App\Modules\KitchenDispatch\Http\Requests\UpdateAdminKitchenStationRequest;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationCategoryRouteResource;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationResource;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminKitchenRoutingController extends Controller
{
    public function __construct(
        private readonly KitchenRoutingService $kitchenRoutingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stations = $this->kitchenRoutingService->listStations();

        return response()->json([
            'data' => KitchenStationResource::collection($stations)->toArray($request),
            'meta' => [
                'count' => $stations->count(),
            ],
        ]);
    }

    public function store(CreateAdminKitchenStationRequest $request): JsonResponse
    {
        $station = $this->kitchenRoutingService->createStation($request->validated());

        return response()->json([
            'data' => (new KitchenStationResource($station))->toArray($request),
        ], 201);
    }

    public function show(int $station_id, Request $request): JsonResponse
    {
        try {
            $station = $this->kitchenRoutingService->findStation($station_id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request);
        }

        return response()->json([
            'data' => (new KitchenStationResource($station))->toArray($request),
        ]);
    }

    public function update(int $station_id, UpdateAdminKitchenStationRequest $request): JsonResponse
    {
        try {
            $station = $this->kitchenRoutingService->updateStation($station_id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request);
        }

        return response()->json([
            'data' => (new KitchenStationResource($station))->toArray($request),
        ]);
    }

    public function routes(int $station_id, Request $request): JsonResponse
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

    public function syncRoutes(int $station_id, SyncAdminKitchenStationRoutesRequest $request): JsonResponse
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
