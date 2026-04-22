<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\KitchenDispatch\Http\Requests\Admin\CreateKitchenStationRequest;
use App\Modules\KitchenDispatch\Http\Requests\Admin\UpdateKitchenStationRequest;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationResource;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenStationController extends Controller
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

    public function store(CreateKitchenStationRequest $request): JsonResponse
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

    public function update(int $station_id, UpdateKitchenStationRequest $request): JsonResponse
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
