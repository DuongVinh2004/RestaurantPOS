<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RuntimeSettingService
{
    private const CACHE_PREFIX = 'runtime_setting:';
    private const CACHE_TTL_SECONDS = 60;
    /** @var array<string,mixed> */
    private array $resolvedValues = [];

    public function int(string $settingKey, int $fallback): int
    {
        $value = $this->value($settingKey);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $fallback;
    }

    public function float(string $settingKey, float $fallback): float
    {
        $value = $this->value($settingKey);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $fallback;
    }

    public function bool(string $settingKey, bool $fallback): bool
    {
        $value = $this->value($settingKey);

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

        return $fallback;
    }

    /**
     * @return mixed
     */
    public function value(string $settingKey): mixed
    {
        if (array_key_exists($settingKey, $this->resolvedValues)) {
            return $this->resolvedValues[$settingKey];
        }

        $cacheKey = self::CACHE_PREFIX . sha1($settingKey);

        try {
            $value = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($settingKey) {
                return $this->fetchSettingValue($settingKey);
            });
        } catch (\Throwable) {
            $value = $this->fetchSettingValue($settingKey);
        }

        $this->resolvedValues[$settingKey] = $value;

        return $value;
    }

    /**
     * @return mixed
     */
    private function fetchSettingValue(string $settingKey): mixed
    {
        try {
            $rawValue = DB::table('settings')
                ->where('setting_key', $settingKey)
                ->value('value_json');
        } catch (\Throwable) {
            return null;
        }

        if ($rawValue === null) {
            return null;
        }

        if (! is_string($rawValue)) {
            return $rawValue;
        }

        $decoded = json_decode($rawValue, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $rawValue;
        }

        if (is_array($decoded) && array_key_exists('value', $decoded)) {
            return $decoded['value'];
        }

        return $decoded;
    }
}
