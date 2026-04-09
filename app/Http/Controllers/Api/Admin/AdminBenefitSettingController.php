<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAdminBenefitSettingRequest;
use App\Http\Resources\Admin\AdminRuntimeSettingResource;
use App\Services\Admin\Benefits\AdminBenefitSettingService;
use Illuminate\Http\JsonResponse;

class AdminBenefitSettingController extends Controller
{
    public function __construct(
        private readonly AdminBenefitSettingService $settingsService,
    ) {}

    public function index(): JsonResponse
    {
        $settings = $this->settingsService->list();

        return response()->json([
            'data' => AdminRuntimeSettingResource::collection(collect($settings))->resolve(),
            'meta' => [
                'action' => 'admin_benefit_runtime_settings',
                'count' => count($settings),
            ],
        ]);
    }

    public function upsert(UpsertAdminBenefitSettingRequest $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);

        $setting = $this->settingsService->upsert(
            payload: $request->validated(),
            actorUserId: $actorUserId,
        );

        return response()->json([
            'data' => (new AdminRuntimeSettingResource($setting))->resolve(),
            'meta' => [
                'action' => 'admin_benefit_runtime_setting_upserted',
            ],
        ]);
    }
}
