<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Application\Services;

use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRuntimeSettingService
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private const DEFINITIONS = [
        'voucher.lock_minutes' => [
            'type' => 'int',
            'config_key' => 'booking.voucher_lock_minutes',
            'min' => 1,
        ],
        'loyalty.enabled' => [
            'type' => 'bool',
            'config_key' => 'booking.loyalty_enabled',
        ],
        'loyalty.redeem_amount_per_point' => [
            'type' => 'float',
            'config_key' => 'booking.loyalty_redeem_amount_per_point',
            'min' => 0.01,
        ],
        'loyalty.earn_amount_per_point' => [
            'type' => 'float',
            'config_key' => 'booking.loyalty_earn_amount_per_point',
            'min' => 0.01,
        ],
        'loyalty.min_redeem_points' => [
            'type' => 'int',
            'config_key' => 'booking.loyalty_min_redeem_points',
            'min' => 1,
        ],
    ];

    public function __construct(
        private readonly RuntimeSettingService $runtimeSettings,
    ) {}

    /**
     * @return list<array<string,mixed>>
     */
    public function listBenefitSettings(): array
    {
        $rows = DB::table('settings')
            ->whereIn('setting_key', array_keys(self::DEFINITIONS))
            ->get()
            ->keyBy('setting_key');

        $result = [];
        foreach (self::DEFINITIONS as $settingKey => $definition) {
            $row = $rows->get($settingKey);
            $defaultValue = config((string) $definition['config_key']);
            $runtimeValue = $row ? $this->decodeStoredValue($row->value_json) : null;

            $result[] = [
                'setting_key' => $settingKey,
                'setting_type' => (string) $definition['type'],
                'default_value' => $defaultValue,
                'runtime_value' => $runtimeValue,
                'effective_value' => $this->effectiveValue($settingKey, $definition, $defaultValue),
                'source' => $runtimeValue !== null ? 'runtime' : 'default',
                'updated_by' => $row?->updated_by !== null ? (int) $row->updated_by : null,
                'updated_at' => $row?->updated_at,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function upsertBenefitSetting(array $payload, ?int $actorUserId = null): array
    {
        $settingKey = trim((string) ($payload['setting_key'] ?? ''));
        $definition = self::DEFINITIONS[$settingKey] ?? null;
        if ($definition === null) {
            throw ValidationException::withMessages([
                'setting_key' => ['This runtime setting is not whitelisted for admin management.'],
            ]);
        }

        $normalizedValue = $this->normalizeValue($definition, $payload['value'] ?? null);

        DB::transaction(function () use ($settingKey, $payload, $actorUserId, $normalizedValue): void {
            $existing = DB::table('settings')
                ->where('setting_key', $settingKey)
                ->lockForUpdate()
                ->first();

            $expectedUpdatedAt = $payload['expected_updated_at'] ?? null;
            if ($existing !== null) {
                if ($expectedUpdatedAt === null || trim((string) $expectedUpdatedAt) === '') {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['expected_updated_at is required when updating an existing runtime setting.'],
                    ]);
                }

                $expectedIso = Carbon::parse((string) $expectedUpdatedAt)->utc()->toIso8601String();
                $currentIso = Carbon::parse((string) $existing->updated_at)->utc()->toIso8601String();
                if ($expectedIso !== $currentIso) {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['Runtime setting has been modified by another operation. Please reload and retry.'],
                    ]);
                }

                DB::table('settings')
                    ->where('setting_key', $settingKey)
                    ->update([
                        'value_json' => json_encode(['value' => $normalizedValue], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_by' => $actorUserId,
                        'updated_at' => Carbon::now('UTC'),
                    ]);
            } else {
                DB::table('settings')->insert([
                    'setting_key' => $settingKey,
                    'value_json' => json_encode(['value' => $normalizedValue], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_by' => $actorUserId,
                    'updated_at' => Carbon::now('UTC'),
                ]);
            }
        });

        $this->runtimeSettings->forget($settingKey);

        foreach ($this->listBenefitSettings() as $setting) {
            if ((string) $setting['setting_key'] === $settingKey) {
                return $setting;
            }
        }

        throw ValidationException::withMessages([
            'setting_key' => ['Unable to reload runtime setting after update.'],
        ]);
    }

    /**
     * @param  array<string,mixed>  $definition
     */
    private function effectiveValue(string $settingKey, array $definition, mixed $defaultValue): mixed
    {
        return match ($definition['type']) {
            'bool' => $this->runtimeSettings->bool($settingKey, (bool) $defaultValue),
            'int' => $this->runtimeSettings->int($settingKey, (int) $defaultValue),
            'float' => $this->runtimeSettings->float($settingKey, (float) $defaultValue),
            default => $this->runtimeSettings->value($settingKey) ?? $defaultValue,
        };
    }

    /**
     * @param  array<string,mixed>  $definition
     */
    private function normalizeValue(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'bool' => $this->normalizeBoolValue($value),
            'int' => $this->normalizeIntValue($value, (int) ($definition['min'] ?? PHP_INT_MIN)),
            'float' => $this->normalizeFloatValue($value, (float) ($definition['min'] ?? -INF)),
            default => $value,
        };
    }

    private function normalizeBoolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        throw ValidationException::withMessages([
            'value' => ['Setting value must be a boolean-compatible value.'],
        ]);
    }

    private function normalizeIntValue(mixed $value, int $min): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
            throw ValidationException::withMessages([
                'value' => ['Setting value must be an integer.'],
            ]);
        }

        $normalized = (int) $value;
        if ($normalized < $min) {
            throw ValidationException::withMessages([
                'value' => [sprintf('Setting value must be at least %d.', $min)],
            ]);
        }

        return $normalized;
    }

    private function normalizeFloatValue(mixed $value, float $min): float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric(trim($value)))) {
            throw ValidationException::withMessages([
                'value' => ['Setting value must be numeric.'],
            ]);
        }

        $normalized = round((float) $value, 4);
        if ($normalized < $min) {
            throw ValidationException::withMessages([
                'value' => [sprintf('Setting value must be at least %.2f.', $min)],
            ]);
        }

        return $normalized;
    }

    private function decodeStoredValue(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (! is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        if (is_array($decoded) && array_key_exists('value', $decoded)) {
            return $decoded['value'];
        }

        return $decoded;
    }
}
