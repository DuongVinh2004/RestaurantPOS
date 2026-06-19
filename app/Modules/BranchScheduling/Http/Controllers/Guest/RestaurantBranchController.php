<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\BranchScheduling\Http\Resources\Guest\RestaurantProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantBranchController extends Controller
{
    public function __construct(
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()->where('is_active', true)->get();

        $data = $branches->map(function (Branch $branch) {
            $context = $this->branchSchedulingPolicyService->resolveContext($branch->branch_id, true);

            return [
                'branch' => $context['branch'],
                'business_hours' => $context['business_hours'],
                'open_status' => $this->branchSchedulingPolicyService->currentOpenStatus($branch->branch_id, null, true),
            ];
        });

        return response()->json([
            'data' => RestaurantProfileResource::collection($data)->resolve($request),
            'meta' => [
                'action' => 'restaurant_branch_index',
            ],
        ]);
    }
}
