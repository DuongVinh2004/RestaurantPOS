<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAdminFinanceTaxProfileRequest;
use App\Services\Finance\FinanceTaxProfileService;
use Illuminate\Http\JsonResponse;

class AdminFinanceSettingsController extends Controller
{
    public function __construct(
        private readonly FinanceTaxProfileService $taxProfileService,
    ) {
    }

    public function showTaxProfile(): JsonResponse
    {
        $setting = $this->taxProfileService->describe();

        return response()->json([
            'data' => $setting,
            'meta' => [
                'action' => 'admin_finance_tax_profile_show',
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function upsertTaxProfile(UpsertAdminFinanceTaxProfileRequest $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $setting = $this->taxProfileService->upsert($request->validated(), $actorUserId);

        return response()->json([
            'data' => $setting,
            'meta' => [
                'action' => 'admin_finance_tax_profile_upserted',
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
