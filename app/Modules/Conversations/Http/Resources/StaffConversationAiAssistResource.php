<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffConversationAiAssistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => (string) ($this['status'] ?? 'unavailable'),
            'feature_key' => (string) ($this['feature_key'] ?? 'staff.conversation_ai_assist'),
            'provider' => $this->nullableString($this['provider'] ?? null),
            'model' => $this->nullableString($this['model'] ?? null),
            'priority' => $this->nullableString($this['priority'] ?? null),
            'summary' => $this->nullableString($this['summary'] ?? null),
            'suggested_actions' => $this->mapActions($this['suggested_actions'] ?? []),
            'risk_flags' => $this->mapRiskFlags($this['risk_flags'] ?? []),
            'fallback_reason_code' => $this->nullableString($this['fallback_reason_code'] ?? null),
            'fallback_reason' => $this->nullableString($this['fallback_reason'] ?? null),
            'disclaimer' => $this->nullableString($this['disclaimer'] ?? null),
            'latency_budget_ms' => isset($this['latency_budget_ms']) ? (int) $this['latency_budget_ms'] : null,
            'cost_tier' => $this->nullableString($this['cost_tier'] ?? null),
            'generated_from' => [
                'message_count' => (int) (($this['generated_from']['message_count'] ?? 0)),
                'customer_message_count' => (int) (($this['generated_from']['customer_message_count'] ?? 0)),
                'internal_note_count' => (int) (($this['generated_from']['internal_note_count'] ?? 0)),
                'analysis_count' => (int) (($this['generated_from']['analysis_count'] ?? 0)),
            ],
        ];
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function mapActions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (array $action): array => [
                'code' => (string) ($action['code'] ?? 'review_thread'),
                'label' => (string) ($action['label'] ?? 'Review timeline'),
                'reason' => $this->nullableString($action['reason'] ?? null),
            ],
            array_values(array_filter($value, 'is_array')),
        ));
    }

    /**
     * @return list<array<string, string>>
     */
    private function mapRiskFlags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $risk): array => [
                'code' => (string) ($risk['code'] ?? 'informational'),
                'label' => (string) ($risk['label'] ?? 'Informational'),
                'severity' => (string) ($risk['severity'] ?? 'low'),
            ],
            array_values(array_filter($value, 'is_array')),
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
