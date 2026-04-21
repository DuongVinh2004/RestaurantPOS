<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\UseCases\Vouchers;

use App\Modules\Promotions\Domain\Models\Voucher;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherManagementService
{
    /**
     * @return list<array<string,mixed>>
     */
    public function list(string $term = ''): array
    {
        return Voucher::query()
            ->when(trim($term) !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('code', 'like', '%'.trim($term).'%')
                        ->orWhere('description', 'like', '%'.trim($term).'%');
                });
            })
            ->orderByDesc('voucher_id')
            ->get()
            ->map(fn (Voucher $voucher) => $this->serializeVoucher($voucher))
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function show(int $voucherId): array
    {
        return $this->serializeVoucher($this->findVoucher($voucherId));
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function store(array $payload, int $actorUserId): array
    {
        $voucher = new Voucher;
        $voucher->fill($this->normalizePayload($payload));
        $voucher->created_by = $actorUserId;
        $voucher->updated_by = $actorUserId;
        $voucher->row_version = 1;
        $voucher->save();

        AuditEvent::info('admin.voucher.created', [
            'voucher_id' => (int) $voucher->voucher_id,
            'code' => (string) $voucher->code,
            '_audit' => [
                'action' => 'master_data.voucher.created',
                'entity_type' => 'voucher',
                'entity_id' => (string) $voucher->voucher_id,
                'after' => $this->auditSnapshot($voucher),
                'summary' => [
                    'code' => (string) $voucher->code,
                    'is_active' => (bool) $voucher->is_active,
                ],
                'actor' => [
                    'type' => 'staff_user',
                    'user_id' => $actorUserId,
                    'key' => 'staff_user:'.$actorUserId,
                ],
            ],
        ]);

        return $this->serializeVoucher($voucher->fresh());
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function update(int $voucherId, array $payload, int $actorUserId): array
    {
        return DB::transaction(function () use ($voucherId, $payload, $actorUserId): array {
            /** @var Voucher|null $voucher */
            $voucher = Voucher::query()->where('voucher_id', $voucherId)->lockForUpdate()->first();
            if (! $voucher) {
                throw (new ModelNotFoundException)->setModel(Voucher::class, [$voucherId]);
            }

            $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
            if ((int) ($voucher->row_version ?? 1) !== $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                ]);
            }

            unset($payload['row_version']);
            $this->assertLinkedFieldMutationAllowed($voucher, $payload);
            $before = $this->auditSnapshot($voucher);

            $voucher->fill($this->normalizePayload($payload));
            $voucher->updated_by = $actorUserId;
            $voucher->row_version = ((int) ($voucher->row_version ?? 1)) + 1;
            $voucher->save();

            AuditEvent::info('admin.voucher.updated', [
                'voucher_id' => (int) $voucher->voucher_id,
                'code' => (string) $voucher->code,
                '_audit' => [
                    'action' => 'master_data.voucher.updated',
                    'entity_type' => 'voucher',
                    'entity_id' => (string) $voucher->voucher_id,
                    'before' => $before,
                    'after' => $this->auditSnapshot($voucher),
                    'summary' => [
                        'code' => (string) $voucher->code,
                        'row_version' => (int) ($voucher->row_version ?? 1),
                    ],
                    'actor' => [
                        'type' => 'staff_user',
                        'user_id' => $actorUserId,
                        'key' => 'staff_user:'.$actorUserId,
                    ],
                ],
            ]);

            return $this->serializeVoucher($voucher->fresh());
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = $payload;

        foreach (['start_date', 'expiry_date'] as $key) {
            if (array_key_exists($key, $normalized) && $normalized[$key] !== null && $normalized[$key] !== '') {
                $normalized[$key] = Carbon::parse((string) $normalized[$key], 'UTC');
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function assertLinkedFieldMutationAllowed(Voucher $voucher, array $payload): void
    {
        $assignedCount = (int) DB::table('user_vouchers')
            ->where('voucher_id', (int) $voucher->voucher_id)
            ->count();

        if ($assignedCount <= 0) {
            return;
        }

        $guardedFields = [
            'discount_type',
            'discount_value',
            'free_item_id',
            'free_item_qty',
            'max_usage',
            'max_usage_per_user',
            'min_spend',
        ];

        $errors = [];
        foreach ($guardedFields as $field) {
            if (array_key_exists($field, $payload) && $this->hasChanged($voucher->{$field}, $payload[$field] ?? null)) {
                $errors[$field] = ['Voucher has already been assigned and linked fields can no longer be changed.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function hasChanged(mixed $current, mixed $incoming): bool
    {
        if ($current === null && ($incoming === null || $incoming === '')) {
            return false;
        }

        if (is_bool($current)) {
            return (bool) $current !== (bool) $incoming;
        }

        if (is_numeric($current) || is_numeric($incoming)) {
            return round((float) $current, 6) !== round((float) $incoming, 6);
        }

        return (string) $current !== (string) $incoming;
    }

    private function findVoucher(int $voucherId): Voucher
    {
        /** @var Voucher|null $voucher */
        $voucher = Voucher::query()->where('voucher_id', $voucherId)->first();
        if (! $voucher) {
            throw (new ModelNotFoundException)->setModel(Voucher::class, [$voucherId]);
        }

        return $voucher;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeVoucher(Voucher $voucher): array
    {
        $assignedCount = (int) DB::table('user_vouchers')
            ->where('voucher_id', (int) $voucher->voucher_id)
            ->count();

        return [
            'voucher_id' => (int) $voucher->voucher_id,
            'code' => (string) $voucher->code,
            'description' => $voucher->description,
            'discount_type' => $voucher->discount_type?->value ?? (string) $voucher->discount_type,
            'discount_value' => $voucher->discount_value !== null ? (string) $voucher->discount_value : null,
            'free_item_id' => $voucher->free_item_id,
            'free_item_qty' => $voucher->free_item_qty,
            'max_usage' => $voucher->max_usage,
            'max_usage_per_user' => $voucher->max_usage_per_user,
            'min_spend' => $voucher->min_spend !== null ? (string) $voucher->min_spend : null,
            'start_date' => $voucher->start_date?->toIso8601String(),
            'expiry_date' => $voucher->expiry_date?->toIso8601String(),
            'created_by' => $voucher->created_by !== null ? (int) $voucher->created_by : null,
            'updated_by' => $voucher->updated_by !== null ? (int) $voucher->updated_by : null,
            'row_version' => (int) ($voucher->row_version ?? 1),
            'availability' => [
                'is_active' => (bool) $voucher->is_active,
                'is_currently_valid' => (bool) $voucher->is_active,
            ],
            'usage_summary' => [
                'assigned_count' => $assignedCount,
            ],
            'created_at' => $voucher->created_at?->toIso8601String(),
            'updated_at' => $voucher->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function auditSnapshot(Voucher $voucher): array
    {
        return [
            'code' => (string) $voucher->code,
            'description' => $voucher->description,
            'discount_type' => $voucher->discount_type?->value ?? (string) $voucher->discount_type,
            'discount_value' => $voucher->discount_value !== null ? (string) $voucher->discount_value : null,
            'min_spend' => $voucher->min_spend !== null ? (string) $voucher->min_spend : null,
            'start_date' => $voucher->start_date?->toIso8601String(),
            'expiry_date' => $voucher->expiry_date?->toIso8601String(),
            'is_active' => (bool) $voucher->is_active,
            'row_version' => (int) ($voucher->row_version ?? 1),
        ];
    }
}
