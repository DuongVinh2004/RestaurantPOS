<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\BenefitsLoyalty\Application\Services\AdminLoyaltyTierService;
use App\Modules\BenefitsLoyalty\Http\Requests\Admin\StoreAdminLoyaltyTierRequest;
use App\Modules\BenefitsLoyalty\Http\Requests\Admin\UpdateAdminLoyaltyTierRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLoyaltyTierController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly AdminLoyaltyTierService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list((string) $request->query('q', ''));

        return response()->json([
            'meta' => [
                'action' => 'admin_loyalty_tiers',
                'total' => count($items),
            ],
            'data' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $tier = collect($this->service->list())->firstWhere('tier_id', $id);
        abort_if($tier === null, 404);

        return response()->json([
            'meta' => [
                'action' => 'admin_loyalty_tier',
            ],
            'data' => $tier,
        ]);
    }

    public function store(StoreAdminLoyaltyTierRequest $request): JsonResponse
    {
        $tier = $this->service->store($request->validated(), $this->resolveStaffActorUserId($request));

        return response()->json([
            'meta' => [
                'action' => 'admin_loyalty_tier_created',
            ],
            'data' => $tier,
        ], 201);
    }

    public function update(int $id, UpdateAdminLoyaltyTierRequest $request): JsonResponse
    {
        $tier = $this->service->update($id, $request->validated(), $this->resolveStaffActorUserId($request));

        return response()->json([
            'meta' => [
                'action' => 'admin_loyalty_tier_updated',
            ],
            'data' => $tier,
        ]);
    }
}
