<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Workflows;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceTaxProfileWorkflow
{
    private const SETTING_KEY = 'finance.tax_invoice_profile';

    /**
     * @return array<string,mixed>
     */
    public function describe(): array
    {
        $row = DB::table('settings')->where('setting_key', self::SETTING_KEY)->first();
        $runtimeProfile = $row !== null ? $this->decodeStoredProfile($row->value_json) : null;
        $defaultProfile = $this->defaultProfile();
        $effectiveProfile = $runtimeProfile !== null ? $runtimeProfile : $defaultProfile;

        return [
            'setting_key' => self::SETTING_KEY,
            'default_profile' => $defaultProfile,
            'runtime_profile' => $runtimeProfile,
            'effective_profile' => $effectiveProfile,
            'source' => $runtimeProfile !== null ? 'runtime' : 'default',
            'updated_by' => $row?->updated_by !== null ? (int) $row->updated_by : null,
            'updated_at' => $row?->updated_at,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function effectiveProfile(): array
    {
        /** @var array<string,mixed> $profile */
        $profile = $this->describe()['effective_profile'];

        return $profile;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function upsert(array $payload, ?int $actorUserId = null): array
    {
        $profile = $this->normalizeProfile($payload);

        DB::transaction(function () use ($payload, $profile, $actorUserId): void {
            $existing = DB::table('settings')
                ->where('setting_key', self::SETTING_KEY)
                ->lockForUpdate()
                ->first();

            $expectedUpdatedAt = $payload['expected_updated_at'] ?? null;
            if ($existing !== null) {
                if ($expectedUpdatedAt === null || trim((string) $expectedUpdatedAt) === '') {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['expected_updated_at is required when updating an existing finance tax profile.'],
                    ]);
                }

                $expectedIso = Carbon::parse((string) $expectedUpdatedAt)->utc()->toIso8601String();
                $currentIso = Carbon::parse((string) $existing->updated_at)->utc()->toIso8601String();
                if ($expectedIso !== $currentIso) {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['Finance tax profile has been modified by another operation. Please reload and retry.'],
                    ]);
                }

                DB::table('settings')
                    ->where('setting_key', self::SETTING_KEY)
                    ->update([
                        'value_json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_by' => $actorUserId,
                        'updated_at' => Carbon::now('UTC'),
                    ]);

                return;
            }

            DB::table('settings')->insert([
                'setting_key' => self::SETTING_KEY,
                'value_json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $actorUserId,
                'updated_at' => Carbon::now('UTC'),
            ]);
        });

        return $this->describe();
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultProfile(): array
    {
        return $this->normalizeProfile((array) config('booking.finance_tax_invoice_profile', []));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeStoredProfile(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->normalizeProfile($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeProfile($decoded);
    }

    /**
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private function normalizeProfile(array $profile): array
    {
        $taxCode = strtoupper(trim((string) ($profile['tax_code'] ?? 'VAT10')));
        $taxName = trim((string) ($profile['tax_name'] ?? 'VAT 10%'));
        $rate = round(max(0.0, min(100.0, (float) ($profile['tax_rate_percentage'] ?? 0.0))), 3);
        $invoicePrefix = strtoupper(trim((string) ($profile['invoice_prefix'] ?? 'INV')));
        $sellerName = trim((string) ($profile['seller_name'] ?? 'RestaurantPOS'));

        if ($taxCode === '' || $taxName === '' || $invoicePrefix === '' || $sellerName === '') {
            throw ValidationException::withMessages([
                'profile' => ['Finance tax profile contains empty required fields.'],
            ]);
        }

        return [
            'tax_code' => $taxCode,
            'tax_name' => $taxName,
            'tax_rate_percentage' => $rate,
            'prices_include_tax' => (bool) ($profile['prices_include_tax'] ?? true),
            'invoice_prefix' => $invoicePrefix,
            'seller_name' => $sellerName,
            'seller_tax_id' => $this->nullableTrimmedString($profile['seller_tax_id'] ?? null),
            'seller_address' => $this->nullableTrimmedString($profile['seller_address'] ?? null),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
