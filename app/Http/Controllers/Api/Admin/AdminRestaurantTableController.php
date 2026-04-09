<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\ListAdminRestaurantTablesRequest;
use App\Http\Requests\Admin\Restaurant\StoreAdminRestaurantTableRequest;
use App\Http\Requests\Admin\Restaurant\UpdateAdminRestaurantTableRequest;
use App\Http\Requests\Admin\Restaurant\DeleteAdminRestaurantTableRequest;
use App\Http\Resources\Admin\AdminRestaurantTableResource;
use App\Http\Resources\Admin\AdminTableTemplateResource;
use App\Services\Admin\Restaurant\AdminRestaurantTableService;
use Illuminate\Http\JsonResponse;

class AdminRestaurantTableController extends Controller
{
    public function __construct(
        private readonly AdminRestaurantTableService $tableService,
    ) {}

    public function index(ListAdminRestaurantTablesRequest $request): JsonResponse
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
            'data' => AdminRestaurantTableResource::collection(collect($result['tables']))->resolve(),
            'meta' => $result['meta'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $table = $this->tableService->showTable($id);

        return response()->json([
            'data' => (new AdminRestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_show',
            ],
        ]);
    }

    public function store(StoreAdminRestaurantTableRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->createTable($payload, $actorUserId);

        return response()->json([
            'data' => (new AdminRestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_created',
            ],
        ], 201);
    }

    public function update(int $id, UpdateAdminRestaurantTableRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->updateTable($id, $payload, $actorUserId);

        return response()->json([
            'data' => (new AdminRestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_updated',
            ],
        ]);
    }

    public function destroy(int $id, DeleteAdminRestaurantTableRequest $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $table = $this->tableService->deleteTable($id, $request->validated(), $actorUserId);

        return response()->json([
            'data' => (new AdminRestaurantTableResource($table))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_deleted',
            ],
        ]);
    }

    public function templates(): JsonResponse
    {
        $templates = $this->tableService->listTemplates();

        return response()->json([
            'data' => AdminTableTemplateResource::collection(collect($templates))->resolve(),
            'meta' => [
                'action' => 'admin_restaurant_table_templates',
                'count' => count($templates),
            ],
        ]);
    }
}
