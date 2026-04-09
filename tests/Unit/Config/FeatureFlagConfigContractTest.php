<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class FeatureFlagConfigContractTest extends TestCase
{
    public function test_rollout_candidate_features_are_registered_with_safe_defaults(): void
    {
        $features = (array) config('feature_flags.features', []);
        $required = [
            'customer.bill_self_payment',
            'waiting_list.advanced_automation',
            'staff.kitchen_dispatch',
            'inventory.uplift',
            'staff.conversation_inbox',
            'staff.conversation_ai_assist',
        ];

        foreach ($required as $featureKey) {
            self::assertArrayHasKey($featureKey, $features);
            self::assertTrue((bool) ($features[$featureKey]['kill_switch'] ?? false));
            self::assertFalse((bool) ($features[$featureKey]['defaults']['*'] ?? true));
            self::assertTrue((bool) ($features[$featureKey]['defaults']['testing'] ?? false));
            self::assertTrue((bool) ($features[$featureKey]['defaults']['local'] ?? false));
            self::assertNotSame('', trim((string) ($features[$featureKey]['disabled_message'] ?? '')));
        }
    }
}
