<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Workflows\FinanceTaxProfileWorkflow;
use App\Modules\Billing\Http\Requests\Admin\UpsertFinanceTaxProfileRequest;
use Illuminate\Http\JsonResponse;

class FinanceTaxProfileController extends Controller
{
    public function __construct(
        private readonly FinanceTaxProfileWorkflow $taxProfileService,
    ) {}

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

    public function upsertTaxProfile(UpsertFinanceTaxProfileRequest $request): JsonResponse
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
