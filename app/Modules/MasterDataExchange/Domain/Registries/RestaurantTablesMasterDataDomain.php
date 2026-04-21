<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Domain\Registries;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableManagementService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableTemplate;
use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Modules\MasterDataExchange\Infrastructure\Internal\AbstractMasterDataDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RestaurantTablesMasterDataDomain extends AbstractMasterDataDomain implements MasterDataDomain
{
    public function __construct(
        private readonly RestaurantTableManagementService $tableService,
    ) {}

    public function key(): string
    {
        return 'restaurant-tables';
    }

    public function label(): string
    {
        return 'Restaurant Tables';
    }

    public function importColumns(): array
    {
        return [
            'branch_code',
            'table_code',
            'template_code',
            'zone',
            'pos_x',
            'pos_y',
            'status',
            'description',
            'price',
            'is_deleted',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'branch_code',
            'table_code',
            'template_code',
        ];
    }

    public function exportRows(string $format): array
    {
        return RestaurantTable::query()
            ->with(['branch', 'template'])
            ->orderBy('table_code')
            ->get()
            ->map(fn (RestaurantTable $table): array => $this->snapshot($table))
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];
        $branchCodes = [];
        $templateCodes = [];

        foreach ($rows as $row) {
            $raw = $this->rawRow($row);
            $branchCode = strtoupper($this->trimmed($raw['branch_code'] ?? ''));
            $templateCode = $this->trimmed($raw['template_code'] ?? '');

            if ($branchCode !== '') {
                $branchCodes[] = $branchCode;
            }

            if ($templateCode !== '') {
                $templateCodes[] = $templateCode;
            }
        }

        $branches = Branch::query()
            ->whereIn('branch_code', array_values(array_unique($branchCodes)))
            ->get()
            ->keyBy('branch_code');
        $templates = TableTemplate::query()
            ->whereIn('template_code', array_values(array_unique($templateCodes)))
            ->get()
            ->keyBy('template_code');

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $branchCode = strtoupper($this->trimmed($raw['branch_code'] ?? ''));
            $tableCode = $this->trimmed($raw['table_code'] ?? '');
            $templateCode = $this->trimmed($raw['template_code'] ?? '');

            try {
                Validator::make($raw, [
                    'branch_code' => ['required', 'string', 'max:50'],
                    'table_code' => ['required', 'string', 'max:50'],
                    'template_code' => ['required', 'string', 'max:50'],
                    'zone' => ['nullable', 'string', 'max:50'],
                    'pos_x' => ['nullable', 'integer'],
                    'pos_y' => ['nullable', 'integer'],
                    'status' => ['nullable', 'string', 'in:Available,Blocked,Maintenance'],
                    'description' => ['nullable', 'string', 'max:400'],
                    'price' => ['nullable', 'numeric', 'min:0'],
                    'is_deleted' => ['nullable', 'boolean'],
                ])->validate();

                if (! $branches->has($branchCode)) {
                    $errors[] = $this->error('branch_code', 'Branch ['.$branchCode.'] does not exist.');
                }

                if (! $templates->has($templateCode)) {
                    $errors[] = $this->error('template_code', 'Table template ['.$templateCode.'] does not exist.');
                }

                $normalized = [
                    'branch_code' => $branchCode,
                    'table_code' => $tableCode,
                    'template_code' => $templateCode,
                    'zone' => $this->nullableString($raw['zone'] ?? null),
                    'pos_x' => $this->integerValue($raw['pos_x'] ?? null),
                    'pos_y' => $this->integerValue($raw['pos_y'] ?? null),
                    'status' => $this->nullableString($raw['status'] ?? null) ?? RestaurantTableStatus::Available->value,
                    'description' => $this->nullableString($raw['description'] ?? null),
                    'price' => $this->decimalValue($raw['price'] ?? null),
                    'is_deleted' => $this->booleanValue($raw['is_deleted'] ?? null, false),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => ['table_code' => $tableCode],
                'match_key_value' => $tableCode,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
                '_branch_id' => $branches->has($branchCode) ? (int) $branches->get($branchCode)->branch_id : null,
                '_template_id' => $templates->has($templateCode) ? (int) $templates->get($templateCode)->template_id : null,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = RestaurantTable::query()
            ->with(['branch', 'template'])
            ->whereIn('table_code', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('table_code');

        $usageByTable = $this->loadUsageSnapshot(
            $existingMap->pluck('table_id')->map(static fn (mixed $value): int => (int) $value)->all()
        );

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var RestaurantTable|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof RestaurantTable ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof RestaurantTable) {
                $this->applyOperationalGuards(
                    $prepared[$index],
                    $existing,
                    $before ?? [],
                    $after,
                    $usageByTable[(int) $existing->table_id] ?? ['has_active_operational_links' => false],
                );

                if ($prepared[$index]['errors'] !== []) {
                    $prepared[$index]['operation'] = 'invalid';
                    $prepared[$index]['status'] = 'invalid';

                    continue;
                }

                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'table_id' => $existing?->table_id !== null ? (int) $existing->table_id : null,
                'row_version' => $existing?->row_version !== null ? (int) $existing->row_version : null,
                'payload' => [
                    'branch_id' => (int) data_get($row, '_branch_id'),
                    'table_code' => $after['table_code'] ?? null,
                    'template_id' => (int) data_get($row, '_template_id'),
                    'zone' => $after['zone'] ?? null,
                    'pos_x' => $after['pos_x'] ?? null,
                    'pos_y' => $after['pos_y'] ?? null,
                    'status' => $after['status'] ?? RestaurantTableStatus::Available->value,
                    'description' => $after['description'] ?? null,
                    'price' => $after['price'] ?? null,
                    'is_deleted' => $after['is_deleted'] ?? false,
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
                $table = $this->tableService->createTable($payload, $actorUserId);
                $created++;
            } else {
                $table = $this->tableService->updateTable(
                    (int) data_get($row, '_apply.table_id'),
                    array_merge($payload, [
                        'row_version' => (int) data_get($row, '_apply.row_version'),
                    ]),
                    $actorUserId,
                );
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'restaurant_table',
                'entity_id' => (string) $table->table_id,
                'operation' => $operation,
                'before' => $before,
                'after' => $this->snapshot($table),
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
     * @param  array<string,mixed>  $preparedRow
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     * @param  array{has_active_operational_links:bool}  $usage
     */
    private function applyOperationalGuards(array &$preparedRow, RestaurantTable $existing, array $before, array $after, array $usage): void
    {
        $currentStatus = (string) ($existing->status?->value ?? $existing->status);
        $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Reserved->value, RestaurantTableStatus::Occupied->value], true);
        $hasLinks = (bool) ($usage['has_active_operational_links'] ?? false);

        $changed = [
            'branch_code' => ($before['branch_code'] ?? null) !== ($after['branch_code'] ?? null),
            'table_code' => ($before['table_code'] ?? null) !== ($after['table_code'] ?? null),
            'template_code' => ($before['template_code'] ?? null) !== ($after['template_code'] ?? null),
            'zone' => ($before['zone'] ?? null) !== ($after['zone'] ?? null),
            'status' => ($before['status'] ?? null) !== ($after['status'] ?? null),
            'is_deleted' => ($before['is_deleted'] ?? null) !== ($after['is_deleted'] ?? null),
        ];

        if ($runtimeOwnedStatus && $changed['status']) {
            $preparedRow['errors'][] = $this->error('status', 'Cannot override a table status that is currently managed by live reservation/order flow.');
        }

        foreach (['branch_code', 'table_code', 'template_code', 'zone', 'is_deleted'] as $field) {
            if ($runtimeOwnedStatus && $changed[$field]) {
                $preparedRow['errors'][] = $this->error($field, 'Cannot change this table attribute while the table is operationally linked.');
            }

            if ($hasLinks && $changed[$field]) {
                $preparedRow['errors'][] = $this->error($field, 'Cannot change this table attribute while the table is operationally linked.');
            }
        }

        if (
            $hasLinks
            && $changed['status']
            && in_array((string) ($after['status'] ?? RestaurantTableStatus::Available->value), [RestaurantTableStatus::Blocked->value, RestaurantTableStatus::Maintenance->value], true)
        ) {
            $preparedRow['errors'][] = $this->error('status', 'Cannot block or mark maintenance while the table still has active reservations or holds.');
        }
    }

    /**
     * @param  list<int>  $tableIds
     * @return array<int, array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool}>
     */
    private function loadUsageSnapshot(array $tableIds): array
    {
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        if ($tableIds === []) {
            return [];
        }

        $now = now('UTC');

        $reservationCounts = DB::table('reservation_tables')
            ->join('reservations', 'reservations.reservation_id', '=', 'reservation_tables.reservation_id')
            ->whereIn('reservation_tables.table_id', $tableIds)
            ->whereIn('reservations.status', ReservationStatus::activeDbValues())
            ->where('reservations.end_time', '>', $now)
            ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
            ->groupBy('reservation_tables.table_id')
            ->pluck('aggregate_count', 'reservation_tables.table_id');

        $holdCounts = collect();
        if (Schema::hasTable('table_holds') && Schema::hasTable('table_hold_details')) {
            $holdCountsQuery = DB::table('table_hold_details')
                ->join('table_holds', 'table_holds.hold_id', '=', 'table_hold_details.hold_id')
                ->whereIn('table_hold_details.table_id', $tableIds)
                ->where('table_holds.end_time', '>', $now)
                ->where(function ($query) use ($now): void {
                    $query
                        ->where(function ($holdQuery) use ($now): void {
                            $holdQuery
                                ->whereIn('table_holds.hold_status', [
                                    TableHoldStatus::Holding->value,
                                    TableHoldStatus::Pending->value,
                                ])
                                ->where('table_holds.expire_at', '>', $now);
                        })
                        ->orWhere(function ($holdQuery): void {
                            $holdQuery
                                ->where('table_holds.hold_status', TableHoldStatus::Confirmed->value)
                                ->whereNotNull('table_holds.confirmed_reservation_id');
                        });
                });

            $holdCounts = $holdCountsQuery
                ->selectRaw('table_hold_details.table_id, COUNT(*) as aggregate_count')
                ->groupBy('table_hold_details.table_id')
                ->pluck('aggregate_count', 'table_hold_details.table_id');
        }

        $orderCounts = collect();
        if (Schema::hasTable('reservation_orders') && Schema::hasTable('reservation_tables')) {
            $orderCounts = DB::table('reservation_tables')
                ->join('reservation_orders', 'reservation_orders.reservation_id', '=', 'reservation_tables.reservation_id')
                ->whereIn('reservation_tables.table_id', $tableIds)
                ->where('reservation_orders.status', ReservationOrderStatus::Active->value)
                ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
                ->groupBy('reservation_tables.table_id')
                ->pluck('aggregate_count', 'reservation_tables.table_id');
        }

        $snapshot = [];
        foreach ($tableIds as $tableId) {
            $reservationCount = (int) ($reservationCounts[$tableId] ?? 0);
            $holdCount = (int) ($holdCounts[$tableId] ?? 0);
            $orderCount = (int) ($orderCounts[$tableId] ?? 0);
            $snapshot[$tableId] = [
                'active_reservation_count' => $reservationCount,
                'active_hold_count' => $holdCount,
                'active_order_count' => $orderCount,
                'has_active_operational_links' => $reservationCount > 0 || $holdCount > 0 || $orderCount > 0,
            ];
        }

        return $snapshot;
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(RestaurantTable $table): array
    {
        return $this->projectSnapshot([
            'branch_code' => $table->relationLoaded('branch')
                ? (string) $table->branch->branch_code
                : (string) ($table->branch()->value('branch_code') ?? ''),
            'table_code' => (string) $table->table_code,
            'template_code' => $table->relationLoaded('template')
                ? (string) ($table->template?->template_code ?? '')
                : (string) ($table->template()->value('template_code') ?? ''),
            'zone' => $table->zone,
            'pos_x' => $table->pos_x !== null ? (int) $table->pos_x : null,
            'pos_y' => $table->pos_y !== null ? (int) $table->pos_y : null,
            'status' => (string) ($table->status?->value ?? $table->status),
            'description' => $table->description,
            'price' => $table->price !== null ? number_format((float) $table->price, 2, '.', '') : null,
            'is_deleted' => (bool) $table->is_deleted,
        ], $this->importColumns());
    }
}
