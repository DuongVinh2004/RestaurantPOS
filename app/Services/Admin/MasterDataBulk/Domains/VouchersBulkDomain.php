<?php

declare(strict_types=1);

namespace App\Services\Admin\MasterDataBulk\Domains;

use App\Enums\VoucherDiscountType;
use App\Models\MenuItem;
use App\Models\Voucher;
use App\Services\Admin\Benefits\AdminVoucherService;
use App\Services\Admin\MasterDataBulk\Contracts\MasterDataBulkDomain;
use App\Services\Admin\MasterDataBulk\Support\AbstractMasterDataBulkDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class VouchersBulkDomain extends AbstractMasterDataBulkDomain implements MasterDataBulkDomain
{
    public function __construct(
        private readonly AdminVoucherService $voucherService,
    ) {
    }

    public function key(): string
    {
        return 'vouchers';
    }

    public function label(): string
    {
        return 'Vouchers';
    }

    public function importColumns(): array
    {
        return [
            'code',
            'description',
            'discount_type',
            'discount_value',
            'free_item_code',
            'free_item_qty',
            'max_usage',
            'max_usage_per_user',
            'min_spend',
            'start_date',
            'expiry_date',
            'is_active',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'code',
            'discount_type',
        ];
    }

    public function exportRows(string $format): array
    {
        return Voucher::query()
            ->with('freeItem')
            ->orderBy('code')
            ->get()
            ->map(fn (Voucher $voucher): array => $this->snapshot($voucher))
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];
        $freeItemCodes = [];

        foreach ($rows as $row) {
            $freeItemCode = $this->nullableString($this->rawRow($row)['free_item_code'] ?? null);
            if ($freeItemCode !== null) {
                $freeItemCodes[] = $freeItemCode;
            }
        }

        $menuItems = MenuItem::query()
            ->whereIn('code', array_values(array_unique($freeItemCodes)))
            ->get()
            ->keyBy('code');

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $code = $this->trimmed($raw['code'] ?? '');
            $freeItemCode = $this->nullableString($raw['free_item_code'] ?? null);
            $discountType = $this->nullableString($raw['discount_type'] ?? null);

            try {
                Validator::make($raw, [
                    'code' => ['required', 'string', 'max:100'],
                    'description' => ['nullable', 'string', 'max:255'],
                    'discount_type' => ['required', 'string', Rule::in(array_map(
                        static fn (VoucherDiscountType $type): string => $type->value,
                        VoucherDiscountType::cases()
                    ))],
                    'discount_value' => ['nullable', 'numeric', 'min:0'],
                    'free_item_code' => ['nullable', 'string', 'max:50'],
                    'free_item_qty' => ['nullable', 'integer', 'min:1'],
                    'max_usage' => ['nullable', 'integer', 'min:1'],
                    'max_usage_per_user' => ['nullable', 'integer', 'min:1'],
                    'min_spend' => ['nullable', 'numeric', 'min:0'],
                    'start_date' => ['nullable', 'date'],
                    'expiry_date' => ['nullable', 'date'],
                    'is_active' => ['nullable', 'boolean'],
                ])->validate();

                if ($freeItemCode !== null && ! $menuItems->has($freeItemCode)) {
                    $errors[] = $this->error('free_item_code', 'Menu item [' . $freeItemCode . '] does not exist.');
                }

                if ($discountType === VoucherDiscountType::FreeItem->value && $freeItemCode === null) {
                    $errors[] = $this->error('free_item_code', 'FreeItem vouchers require free_item_code.');
                }

                if ($discountType === VoucherDiscountType::FreeItem->value && (int) ($raw['free_item_qty'] ?? 0) <= 0) {
                    $errors[] = $this->error('free_item_qty', 'FreeItem vouchers require free_item_qty greater than zero.');
                }

                $normalized = [
                    'code' => $code,
                    'description' => $this->nullableString($raw['description'] ?? null),
                    'discount_type' => $discountType,
                    'discount_value' => $this->decimalValue($raw['discount_value'] ?? null) ?? '0.00',
                    'free_item_code' => $freeItemCode,
                    'free_item_qty' => $this->integerValue($raw['free_item_qty'] ?? null),
                    'max_usage' => $this->integerValue($raw['max_usage'] ?? null),
                    'max_usage_per_user' => $this->integerValue($raw['max_usage_per_user'] ?? null),
                    'min_spend' => $this->decimalValue($raw['min_spend'] ?? null) ?? '0.00',
                    'start_date' => $this->isoDateTime($raw['start_date'] ?? null),
                    'expiry_date' => $this->isoDateTime($raw['expiry_date'] ?? null),
                    'is_active' => $this->booleanValue($raw['is_active'] ?? null, true),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => ['code' => $code],
                'match_key_value' => $code,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
                '_free_item_id' => $freeItemCode !== null && $menuItems->has($freeItemCode)
                    ? (int) $menuItems->get($freeItemCode)->item_id
                    : null,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = Voucher::query()
            ->with('freeItem')
            ->whereIn('code', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('code');

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var Voucher|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof Voucher ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof Voucher) {
                $assignedCount = (int) DB::table('user_vouchers')
                    ->where('voucher_id', (int) $existing->voucher_id)
                    ->count();

                if ($assignedCount > 0 && $before !== null) {
                    foreach ([
                        'discount_type',
                        'discount_value',
                        'free_item_code',
                        'free_item_qty',
                        'max_usage',
                        'max_usage_per_user',
                        'min_spend',
                    ] as $guardedField) {
                        if (($before[$guardedField] ?? null) !== ($after[$guardedField] ?? null)) {
                            $prepared[$index]['errors'][] = $this->error($guardedField, 'Voucher has already been assigned and linked fields can no longer be changed.');
                        }
                    }
                }

                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            if ($prepared[$index]['errors'] !== []) {
                $prepared[$index]['operation'] = 'invalid';
                $prepared[$index]['status'] = 'invalid';
                continue;
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'voucher_id' => $existing?->voucher_id !== null ? (int) $existing->voucher_id : null,
                'row_version' => $existing?->row_version !== null ? (int) $existing->row_version : null,
                'payload' => [
                    'code' => $after['code'] ?? null,
                    'description' => $after['description'] ?? null,
                    'discount_type' => $after['discount_type'] ?? null,
                    'discount_value' => $after['discount_value'] ?? null,
                    'free_item_id' => data_get($row, '_free_item_id'),
                    'free_item_qty' => $after['free_item_qty'] ?? null,
                    'max_usage' => $after['max_usage'] ?? null,
                    'max_usage_per_user' => $after['max_usage_per_user'] ?? null,
                    'min_spend' => $after['min_spend'] ?? '0.00',
                    'start_date' => $after['start_date'] ?? null,
                    'expiry_date' => $after['expiry_date'] ?? null,
                    'is_active' => $after['is_active'] ?? true,
                ],
            ];
        }

        return [
            'rows' => $prepared,
            'summary' => $this->makeSummary($prepared),
        ];
    }

    public function apply(array $rows, int $actorUserId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $changes = [];

        foreach ($rows as $row) {
            $operation = (string) ($row['operation'] ?? 'invalid');
            if ($operation === 'invalid') {
                continue;
            }

            if ($operation === 'noop') {
                $unchanged++;
                continue;
            }

            $payload = (array) data_get($row, '_apply.payload', []);
            $before = is_array($row['before'] ?? null) ? $row['before'] : null;

            if ($operation === 'create') {
                $voucher = $this->voucherService->store($payload, $actorUserId);
                $entityId = (string) ($voucher['voucher_id'] ?? '');
                $after = $this->projectSnapshot($voucher, $this->importColumns());
                $created++;
            } else {
                $voucher = $this->voucherService->update(
                    (int) data_get($row, '_apply.voucher_id'),
                    array_merge($payload, [
                        'row_version' => (int) data_get($row, '_apply.row_version'),
                    ]),
                    $actorUserId,
                );
                $entityId = (string) ($voucher['voucher_id'] ?? '');
                $after = $this->projectSnapshot($voucher, $this->importColumns());
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'voucher',
                'entity_id' => $entityId,
                'operation' => $operation,
                'before' => $before,
                'after' => $after,
            ];
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'changes' => $changes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(Voucher $voucher): array
    {
        return $this->projectSnapshot([
            'code' => (string) $voucher->code,
            'description' => $voucher->description,
            'discount_type' => $voucher->discount_type?->value ?? (string) $voucher->discount_type,
            'discount_value' => $voucher->discount_value !== null ? number_format((float) $voucher->discount_value, 2, '.', '') : null,
            'free_item_code' => $voucher->relationLoaded('freeItem')
                ? ($voucher->freeItem?->code !== null ? (string) $voucher->freeItem->code : null)
                : ($voucher->freeItem()->value('code') !== null ? (string) $voucher->freeItem()->value('code') : null),
            'free_item_qty' => $voucher->free_item_qty !== null ? (int) $voucher->free_item_qty : null,
            'max_usage' => $voucher->max_usage !== null ? (int) $voucher->max_usage : null,
            'max_usage_per_user' => $voucher->max_usage_per_user !== null ? (int) $voucher->max_usage_per_user : null,
            'min_spend' => $voucher->min_spend !== null ? number_format((float) $voucher->min_spend, 2, '.', '') : '0.00',
            'start_date' => $this->isoDateTime($voucher->start_date),
            'expiry_date' => $this->isoDateTime($voucher->expiry_date),
            'is_active' => (bool) $voucher->is_active,
        ], $this->importColumns());
    }
}
