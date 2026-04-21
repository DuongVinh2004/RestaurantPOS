<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Domain\Registries;

use App\Modules\Loyalty\Domain\Models\LoyaltyTier;
use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Modules\MasterDataExchange\Infrastructure\Internal\AbstractMasterDataDomain;
use App\Modules\Loyalty\Application\UseCases\Tiers\LoyaltyTierManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

final class LoyaltyTiersMasterDataDomain extends AbstractMasterDataDomain implements MasterDataDomain
{
    public function __construct(
        private readonly LoyaltyTierManagementService $tierService,
    ) {
    }

    public function key(): string
    {
        return 'loyalty-tiers';
    }

    public function label(): string
    {
        return 'Loyalty Tiers';
    }

    public function importColumns(): array
    {
        return [
            'tier_code',
            'tier_name',
            'min_points',
            'benefits_json',
            'is_active',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'tier_code',
            'tier_name',
            'min_points',
        ];
    }

    public function exportRows(string $format): array
    {
        return LoyaltyTier::query()
            ->orderBy('min_points')
            ->get()
            ->map(function (LoyaltyTier $tier) use ($format): array {
                $snapshot = $this->snapshot($tier);
                if ($format === 'csv' && is_array($snapshot['benefits_json'] ?? null)) {
                    $snapshot['benefits_json'] = json_encode($snapshot['benefits_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                return $snapshot;
            })
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $tierCode = $this->trimmed($raw['tier_code'] ?? '');

            try {
                Validator::make($raw, [
                    'tier_code' => ['required', 'string', 'max:50'],
                    'tier_name' => ['required', 'string', 'max:100'],
                    'min_points' => ['required', 'integer', 'min:0'],
                    'is_active' => ['nullable', 'boolean'],
                ])->validate();

                try {
                    $benefits = $this->jsonValue($raw['benefits_json'] ?? null);
                } catch (JsonException) {
                    $errors[] = $this->error('benefits_json', 'benefits_json must be valid json when provided as text.');
                    $benefits = null;
                }

                if ($benefits !== null && ! is_array($benefits)) {
                    $errors[] = $this->error('benefits_json', 'benefits_json must decode to an object or array.');
                }

                $normalized = [
                    'tier_code' => $tierCode,
                    'tier_name' => $this->trimmed($raw['tier_name'] ?? ''),
                    'min_points' => (int) ($raw['min_points'] ?? 0),
                    'benefits_json' => is_array($benefits) ? $benefits : null,
                    'is_active' => $this->booleanValue($raw['is_active'] ?? null, true),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => ['tier_code' => $tierCode],
                'match_key_value' => $tierCode,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = LoyaltyTier::query()
            ->whereIn('tier_code', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('tier_code');

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var LoyaltyTier|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof LoyaltyTier ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof LoyaltyTier) {
                if (($before['is_active'] ?? true) !== false && ($after['is_active'] ?? true) === false) {
                    $currentUsers = (int) DB::table('users')
                        ->where('current_tier_id', (int) $existing->tier_id)
                        ->count();

                    if ($currentUsers > 0) {
                        $prepared[$index]['errors'][] = $this->error('is_active', 'Cannot deactivate a loyalty tier that is currently assigned to users.');
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
                'tier_id' => $existing?->tier_id !== null ? (int) $existing->tier_id : null,
                'row_version' => $existing?->row_version !== null ? (int) $existing->row_version : null,
                'payload' => $after,
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
                $tier = $this->tierService->store($payload, $actorUserId);
                $entityId = (string) ($tier['tier_id'] ?? '');
                $after = $this->projectSnapshot($tier, $this->importColumns());
                $created++;
            } else {
                $tier = $this->tierService->update(
                    (int) data_get($row, '_apply.tier_id'),
                    array_merge($payload, [
                        'row_version' => (int) data_get($row, '_apply.row_version'),
                    ]),
                    $actorUserId,
                );
                $entityId = (string) ($tier['tier_id'] ?? '');
                $after = $this->projectSnapshot($tier, $this->importColumns());
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'loyalty_tier',
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
    private function snapshot(LoyaltyTier $tier): array
    {
        return $this->projectSnapshot([
            'tier_code' => (string) $tier->tier_code,
            'tier_name' => (string) $tier->tier_name,
            'min_points' => (int) $tier->min_points,
            'benefits_json' => $tier->benefits_json,
            'is_active' => (bool) $tier->is_active,
        ], $this->importColumns());
    }
}
