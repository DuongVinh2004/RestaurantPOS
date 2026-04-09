<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminBranchRequest;
use App\Http\Requests\Admin\ListAdminBranchesRequest;
use App\Http\Requests\Admin\UpdateAdminBranchRequest;
use App\Http\Resources\BranchResource;
use App\Services\Branch\BranchManagementService;
use Illuminate\Http\JsonResponse;

class AdminBranchController extends Controller
{
    public function __construct(
        private readonly BranchManagementService $branchManagementService,
    ) {
    }

    public function index(ListAdminBranchesRequest $request): JsonResponse
    {
        $branches = $this->branchManagementService->listBranches($request->validated());

        return response()->json([
            'data' => BranchResource::collection($branches)->toArray($request),
            'meta' => [
                'action' => 'admin_branches_index',
                'count' => $branches->count(),
            ],
        ]);
    }

    public function show(int $id, ListAdminBranchesRequest $request): JsonResponse
    {
        $branch = $this->branchManagementService->showBranch($id);

        return response()->json([
            'data' => (new BranchResource($branch))->toArray($request),
            'meta' => [
                'action' => 'admin_branches_show',
            ],
        ]);
    }

    public function store(CreateAdminBranchRequest $request): JsonResponse
    {
        $branch = $this->branchManagementService->createBranch(
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => (new BranchResource($branch))->toArray($request),
            'meta' => [
                'action' => 'admin_branches_created',
            ],
        ], 201);
    }

    public function update(int $id, UpdateAdminBranchRequest $request): JsonResponse
    {
        $branch = $this->branchManagementService->updateBranch(
            $id,
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => (new BranchResource($branch))->toArray($request),
            'meta' => [
                'action' => 'admin_branches_updated',
            ],
        ]);
    }
}
