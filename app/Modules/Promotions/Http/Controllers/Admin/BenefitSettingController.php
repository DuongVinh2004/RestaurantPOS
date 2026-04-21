<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Loyalty\Application\UseCases\Settings\LoyaltyRuntimeSettingService;
use App\Modules\Promotions\Application\UseCases\Benefits\BenefitRuntimeSettingService;
use App\Modules\Promotions\Http\Requests\Admin\UpsertBenefitSettingRequest;
use App\Modules\Promotions\Http\Resources\Admin\RuntimeSettingResource;
use Illuminate\Http\JsonResponse;

class BenefitSettingController extends Controller
{
    public function __construct(
        private readonly BenefitRuntimeSettingService $promotionSettingsService,
        private readonly LoyaltyRuntimeSettingService $loyaltySettingsService,
    ) {}

    public function index(): JsonResponse
    {
        $settings = array_merge(
            $this->loyaltySettingsService->listLoyaltySettings(),
            $this->promotionSettingsService->list(),
        );

        return response()->json([
            'data' => RuntimeSettingResource::collection(collect($settings))->resolve(),
            'meta' => [
                'action' => 'admin_benefit_runtime_settings',
                'count' => count($settings),
            ],
        ]);
    }

    public function upsert(UpsertBenefitSettingRequest $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $payload = $request->validated();
        $settingKey = (string) $payload['setting_key'];

        $setting = in_array($settingKey, LoyaltyRuntimeSettingService::SETTING_KEYS, true)
            ? $this->loyaltySettingsService->upsertLoyaltySetting($payload, $actorUserId)
            : $this->promotionSettingsService->upsert($payload, $actorUserId);

        return response()->json([
            'data' => (new RuntimeSettingResource($setting))->resolve(),
            'meta' => [
                'action' => 'admin_benefit_runtime_setting_upserted',
            ],
        ]);
    }
}
