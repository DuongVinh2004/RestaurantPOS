<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableManagementService;
use App\Modules\BranchScheduling\Http\Requests\Admin\CreateRestaurantTableRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\DeleteRestaurantTableRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\ListRestaurantTablesRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\UpdateRestaurantTableRequest;
use App\Modules\BranchScheduling\Http\Resources\Admin\RestaurantTableResource;
use Illuminate\Http\JsonResponse;

class RestaurantTableController extends Controller
{
    public function __construct(
        private readonly RestaurantTableManagementService $tableService,
    ) {}

    public function index(ListRestaurantTablesRequest $request): JsonResponse
    {
        $result = $this->tableService->listTables([
            'zone' => $request->query('zone'),
            'status' => $request->query('status'),
            'template_id' => $request->query('template_id'),
            'include_deleted' => $request->boolean('include_deleted', false),
            'q' => $request->query('q'),
            'branch_id' => $request->query('branch_id'),
        ]);

        return response()->json([
            'data' => RestaurantTableResource::collection(collect($result['tables']))->resolve(),
            'meta' => $result['meta'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $table = $this->tableService->showTable($id);

        return response()->json([
            'data' => (new RestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_show',
            ],
        ]);
    }

    public function store(CreateRestaurantTableRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->createTable($payload, $actorUserId);

        return response()->json([
            'data' => (new RestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_created',
            ],
        ], 201);
    }

    public function update(int $id, UpdateRestaurantTableRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->updateTable($id, $payload, $actorUserId);

        return response()->json([
            'data' => (new RestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_updated',
            ],
        ]);
    }

    public function destroy(int $id, DeleteRestaurantTableRequest $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->deleteTable($id, $request->validated(), $actorUserId);

        return response()->json([
            'data' => (new RestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_deleted',
            ],
        ]);
    }
}
