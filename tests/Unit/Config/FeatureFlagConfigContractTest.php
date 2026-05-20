<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class FeatureFlagConfigContractTest extends TestCase
{
    public function test_rollout_candidate_features_are_registered_with_safe_defaults(): void
    {
        $features = (array) config('feature_flags.features', []);
        $required = $this->requiredRolloutFeatureKeys();

        foreach ($required as $featureKey) {
            self::assertArrayHasKey($featureKey, $features);
            self::assertTrue((bool) ($features[$featureKey]['kill_switch'] ?? false));
            self::assertFalse((bool) ($features[$featureKey]['defaults']['*'] ?? true));
            self::assertTrue((bool) ($features[$featureKey]['defaults']['testing'] ?? false));
            self::assertTrue((bool) ($features[$featureKey]['defaults']['local'] ?? false));
            self::assertNotSame('', trim((string) ($features[$featureKey]['disabled_message'] ?? '')));
        }
    }

    public function test_rollout_flag_docs_and_skill_reference_cover_registered_features(): void
    {
        $documents = [
            'docs/runbooks/feature-flags-rollout-guide.md',
            'docs/runbooks/booking-launch-readiness.md',
            '.agents/skills/restaurantpos-feature-flag-rollout/references/flags.md',
        ];

        foreach ($documents as $document) {
            $contents = (string) file_get_contents(base_path($document));

            foreach ($this->requiredRolloutFeatureKeys() as $featureKey) {
                self::assertStringContainsString(
                    $featureKey,
                    $contents,
                    sprintf('%s must document rollout flag %s.', $document, $featureKey),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function requiredRolloutFeatureKeys(): array
    {
        return [
            'customer.bill_self_payment',
            'waiting_list.advanced_automation',
            'staff.kitchen_dispatch',
            'inventory.uplift',
            'staff.conversation_inbox',
            'staff.conversation_ai_assist',
        ];
    }
}
