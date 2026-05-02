<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Http\Resources\Guest\RestaurantProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantProfileController extends Controller
{
    public function __construct(
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $context = $this->branchSchedulingPolicyService->resolveContext(null, true);

        return response()->json([
            'data' => (new RestaurantProfileResource([
                'branch' => $context['branch'],
                'business_hours' => $context['business_hours'],
                'open_status' => $this->branchSchedulingPolicyService->currentOpenStatus(null, null, true),
            ]))->toArray($request),
            'meta' => [
                'action' => 'restaurant_profile_show',
            ],
        ]);
    }
}
