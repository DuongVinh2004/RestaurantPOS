<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\BranchManagementService;
use App\Modules\BranchScheduling\Http\Requests\Admin\CreateBranchRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\ListBranchesRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\UpdateBranchRequest;
use App\Modules\BranchScheduling\Http\Resources\Admin\BranchResource;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchManagementService $branchManagementService,
    ) {
    }

    public function index(ListBranchesRequest $request): JsonResponse
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

    public function show(int $id, ListBranchesRequest $request): JsonResponse
    {
        $branch = $this->branchManagementService->showBranch($id);

        return response()->json([
            'data' => (new BranchResource($branch))->toArray($request),
            'meta' => [
                'action' => 'admin_branches_show',
            ],
        ]);
    }

    public function store(CreateBranchRequest $request): JsonResponse
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

    public function update(int $id, UpdateBranchRequest $request): JsonResponse
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
