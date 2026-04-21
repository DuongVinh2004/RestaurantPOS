<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\UseCases\Benefits;

use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BenefitRuntimeSettingService
{
    public const SETTING_KEYS = [
        'voucher.lock_minutes',
    ];

    public function __construct(
        private readonly RuntimeSettingService $runtimeSettings,
    ) {}

    /**
     * @return list<array<string,mixed>>
     */
    public function list(): array
    {
        return array_map(function (string $settingKey): array {
            $row = $this->findSettingRow($settingKey);
            $effectiveValue = $this->effectiveValue($settingKey);

            return [
                'setting_key' => $settingKey,
                'effective_value' => $effectiveValue,
                'source' => $row !== null ? 'runtime' : 'default',
                'updated_by' => $row?->updated_by !== null ? (int) $row->updated_by : null,
                'updated_at' => isset($row->updated_at) ? Carbon::parse((string) $row->updated_at, 'UTC')->toIso8601String() : null,
            ];
        }, self::SETTING_KEYS);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function upsert(array $payload, int $actorUserId): array
    {
        $this->ensureSettingsTableReady();

        $settingKey = (string) $payload['setting_key'];
        $expectedUpdatedAt = $payload['expected_updated_at'] ?? null;
        $value = $payload['value'];

        $result = DB::transaction(function () use ($settingKey, $expectedUpdatedAt, $value, $actorUserId): array {
            $existing = DB::table('settings')
                ->where('setting_key', $settingKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $currentUpdatedAt = Carbon::parse((string) $existing->updated_at, 'UTC')->toIso8601String();
                if ($expectedUpdatedAt === null || (string) $expectedUpdatedAt !== $currentUpdatedAt) {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['Setting was updated by another writer. Reload and retry with the latest updated_at value.'],
                    ]);
                }
            }

            $now = Carbon::now('UTC');
            $encoded = json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            DB::table('settings')->updateOrInsert(
                ['setting_key' => $settingKey],
                [
                    'value_json' => $encoded,
                    'updated_by' => $actorUserId,
                    'updated_at' => $now,
                ]
            );

            $this->runtimeSettings->forget($settingKey);

            return [
                'setting_key' => $settingKey,
                'effective_value' => $this->effectiveValue($settingKey),
                'source' => 'runtime',
                'updated_by' => $actorUserId,
                'updated_at' => $now->toIso8601String(),
            ];
        });

        return $result;
    }

    private function effectiveValue(string $settingKey): mixed
    {
        return match ($settingKey) {
            'voucher.lock_minutes' => $this->runtimeSettings->int($settingKey, (int) config('booking.voucher_lock_minutes', 5)),
            default => $this->runtimeSettings->value($settingKey),
        };
    }

    private function findSettingRow(string $settingKey): ?object
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        return DB::table('settings')->where('setting_key', $settingKey)->first();
    }

    private function ensureSettingsTableReady(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        throw ValidationException::withMessages([
            'setting_key' => ['Settings storage is not available.'],
        ]);
    }
}
